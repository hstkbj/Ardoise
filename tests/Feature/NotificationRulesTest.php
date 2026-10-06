<?php

namespace Tests\Feature;

use App\Models\Tenant\Student;
use App\Models\Tenant\User;
use App\Notifications\SchoolNotification;
use App\Notifications\SmsChannel;
use App\Services\Notifier;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\InteractsWithTenantSchool;
use Tests\TestCase;

/** Destinataires et canaux des notifications réglés par l'école ; envoi en file d'attente. */
class NotificationRulesTest extends TestCase
{
    use InteractsWithTenantSchool, RefreshDatabase;

    protected function tearDown(): void
    {
        $this->tearDownSchool();
        parent::tearDown();
    }

    protected function recordAbsence(string $token, array $data): void
    {
        $this->withToken($token)->postJson('/api/v1/attendance/session', [
            'class_id' => $data['class_id'],
            'date' => now()->toDateString(),
            'slot' => '08:00',
            'records' => [['student_id' => $data['student_id'], 'status' => 'absent']],
        ])->assertOk();
    }

    public function test_by_default_an_absence_is_sent_to_the_parents_in_app_and_by_sms(): void
    {
        Notification::fake();
        $this->createSchool();
        $data = $this->seedSchoolData();

        $this->recordAbsence($this->tokenFor('school_admin'), $data);

        $this->inSchool(function () use ($data) {
            Notification::assertSentTo(User::find($data['parent_user_id']), SchoolNotification::class, function (SchoolNotification $notification, array $channels) {
                return $notification->type === 'attendance'
                    && $notification->link === '/parent/attendance'
                    && in_array('database', $channels, true)
                    && in_array(SmsChannel::class, $channels, true);
            });
            Notification::assertNotSentTo(User::find($data['head_teacher_user_id']), SchoolNotification::class);
        });
    }

    public function test_the_school_can_send_absences_to_the_head_teacher_instead_of_parents(): void
    {
        Notification::fake();
        $this->createSchool();
        $data = $this->seedSchoolData();
        $admin = $this->tokenFor('school_admin');

        $rules = $this->withToken($admin)->putJson('/api/v1/settings/notification_rules', [
            'rules' => ['attendance.recorded' => ['recipients' => ['head_teacher'], 'channels' => ['in_app']]],
        ])->assertOk()->json('data.rules');
        $this->assertSame(['recipients' => ['head_teacher'], 'channels' => ['in_app']], $rules['attendance.recorded']);

        $this->recordAbsence($admin, $data);

        $this->inSchool(function () use ($data) {
            Notification::assertSentTo(User::find($data['head_teacher_user_id']), SchoolNotification::class, fn ($n, array $channels) => $channels === ['database'] && $n->link === '/teacher/attendance/history');
            Notification::assertNotSentTo(User::find($data['parent_user_id']), SchoolNotification::class);
        });
    }

    public function test_sms_is_not_used_when_the_plan_excludes_it(): void
    {
        Notification::fake();
        $this->createSchool();
        $data = $this->seedSchoolData();
        $this->tenant->plan->update(['features' => ['grades', 'attendance', 'parent_portal']]);

        $this->recordAbsence($this->tokenFor('school_admin'), $data);

        $this->inSchool(fn () => Notification::assertSentTo(User::find($data['parent_user_id']), SchoolNotification::class, fn ($n, array $channels) => ! in_array(SmsChannel::class, $channels, true)));
    }

    public function test_queued_notifications_are_delivered_in_the_right_school_database(): void
    {
        config(['queue.default' => 'database']);
        $this->createSchool();
        $data = $this->seedSchoolData();

        $this->inSchool(fn () => app(Notifier::class)->event('grades.published', 'Nouvelle note', 'Maths : 16/20', Student::find($data['student_id']), ['parent' => '/parent/grades']));

        $this->assertSame([$this->tenant->id], DB::connection('central')->table('jobs')->pluck('payload')->map(fn ($payload) => json_decode($payload, true)['tenant_id'] ?? null)->unique()->values()->all());
        $this->assertNull(app(TenantManager::class)->current());

        $this->artisan('queue:work', ['--once' => true, '--stop-when-empty' => true])->assertSuccessful();

        $this->inSchool(fn () => $this->assertSame(1, User::find($data['parent_user_id'])->notifications()->where('data', 'like', '%Nouvelle note%')->count()));
    }
}
