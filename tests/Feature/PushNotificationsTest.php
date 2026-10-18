<?php

namespace Tests\Feature;

use App\Models\Tenant\Assessment;
use App\Models\Tenant\ParentProfile;
use App\Models\Tenant\PushToken;
use App\Models\Tenant\Setting;
use App\Models\Tenant\User;
use App\Services\ParentAccessService;
use App\Support\NotificationEvents;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithTenantSchool;
use Tests\TestCase;

/** Notifications push de l'application parents (service Expo). */
class PushNotificationsTest extends TestCase
{
    use InteractsWithTenantSchool, RefreshDatabase;

    protected const TOKEN = 'ExponentPushToken[abcdefghijklmnop123]';

    protected function tearDown(): void
    {
        $this->tearDownSchool();
        parent::tearDown();
    }

    protected function fakeExpo(string $status = 'ok', ?string $error = null): void
    {
        $ticket = $status === 'ok' ? ['status' => 'ok', 'id' => 'ticket-1'] : ['status' => 'error', 'message' => 'not registered', 'details' => ['error' => $error]];
        Http::fake(['exp.host/*' => fn (Request $request) => Http::response(['data' => array_fill(0, count($request->data()), $ticket)])]);
    }

    /** Évaluation en brouillon de la 1re matière, notes de tous les élèves de la classe. */
    protected function draftAssessment(array $data, array $scores, string $title = 'Interrogation 2'): int
    {
        return $this->inSchool(function () use ($data, $scores, $title) {
            $assessment = Assessment::create([
                'class_subject_id' => $data['class_subject_ids'][0], 'term_id' => $data['term_id'], 'title' => $title, 'type' => 'interrogation',
                'date' => now()->toDateString(), 'coefficient' => 1, 'max_score' => 20, 'status' => 'draft',
            ]);
            foreach ($scores as $key => $score) {
                $assessment->grades()->create(['student_id' => $data['students'][$key], 'score' => $score]);
            }

            return $assessment->id;
        });
    }

    public function test_the_parent_app_registers_and_removes_its_phone(): void
    {
        $this->createSchool();
        $ids = $this->seedSchoolData();
        $parent = $this->withToken($this->tokenFor('parent', 'parent@ecole.test'));

        $parent->postJson('/api/v1/parent/push-token', ['token' => 'n-importe-quoi'])->assertUnprocessable()->assertJsonValidationErrors('token');
        $parent->postJson('/api/v1/parent/push-token', ['token' => self::TOKEN, 'platform' => 'android', 'device_name' => 'Tecno Spark'])->assertOk();
        $parent->postJson('/api/v1/parent/push-token', ['token' => self::TOKEN, 'platform' => 'android'])->assertOk();

        $this->assertSame(1, $this->inSchool(fn () => PushToken::where('user_id', $ids['parent_user_id'])->count()));

        $parent->deleteJson('/api/v1/parent/push-token', ['token' => self::TOKEN])->assertOk();
        $this->assertSame(0, $this->inSchool(fn () => PushToken::count()));
    }

    public function test_validated_grades_are_pushed_to_the_parent_with_the_child_score(): void
    {
        $this->createSchool();
        $data = $this->seedGradedClass($this->seedSchoolData());
        $this->inSchool(fn () => PushToken::create(['user_id' => User::where('email', 'parent@ecole.test')->value('id'), 'token' => self::TOKEN]));
        $assessment = $this->draftAssessment($data, ['awa' => 14.5, 'koffi' => 11, 'binta' => 9]);
        $this->fakeExpo();

        $this->withToken($this->tokenFor('school_admin'))->postJson("/api/v1/assessments/{$assessment}/validate")->assertOk();

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request) use ($data) {
            $message = json_decode($request->body(), true)[0];

            return $request->url() === 'https://exp.host/--/api/v2/push/send'
                && $message['to'] === self::TOKEN
                && $message['title'] === 'Awa · Maths'
                && $message['body'] === 'Interrogation 2 : 14,5/20'
                && $message['data']['type'] === 'grade'
                && $message['data']['student_id'] === $data['students']['awa'];
        });

        // La notification reste aussi dans l'application, avec le même texte
        $this->assertSame('Interrogation 2 : 14,5/20', $this->inSchool(fn () => User::where('email', 'parent@ecole.test')->first()->notifications()->first()->data['body']));
    }

    public function test_an_uninstalled_app_is_forgotten(): void
    {
        $this->createSchool();
        $data = $this->seedGradedClass($this->seedSchoolData());
        $this->inSchool(fn () => PushToken::create(['user_id' => User::where('email', 'parent@ecole.test')->value('id'), 'token' => self::TOKEN]));
        $this->fakeExpo('error', 'DeviceNotRegistered');

        $this->withToken($this->tokenFor('school_admin'))->postJson('/api/v1/assessments/'.$this->draftAssessment($data, ['awa' => 12, 'koffi' => 10, 'binta' => 8]).'/validate')->assertOk();

        $this->assertSame(0, $this->inSchool(fn () => PushToken::count()));
    }

    public function test_schools_that_saved_their_rules_before_push_get_it_until_they_untick_it(): void
    {
        $this->createSchool();
        $this->seedSchoolData();
        $this->inSchool(fn () => Setting::put('notification_rules', ['rules' => ['grades.published' => ['recipients' => ['parents'], 'channels' => ['in_app']]]]));

        $this->assertContains('push', $this->inSchool(fn () => NotificationEvents::rule('grades.published')['channels']));

        $rules = $this->withToken($this->tokenFor('school_admin'))->getJson('/api/v1/settings/notification_rules')->json('data.rules');
        $rules['grades.published']['channels'] = ['in_app'];
        $this->withToken($this->tokenFor('school_admin'))->putJson('/api/v1/settings/notification_rules', ['rules' => $rules])->assertOk();

        $this->assertSame(['in_app'], $this->inSchool(fn () => NotificationEvents::rule('grades.published')['channels']));
        $this->assertContains('push', $this->inSchool(fn () => NotificationEvents::rule('attendance.recorded')['channels']));
    }

    public function test_revoking_the_parent_code_stops_the_notifications(): void
    {
        $this->createSchool();
        $this->seedSchoolData();

        $this->inSchool(function () {
            $user = User::where('email', 'parent@ecole.test')->first();
            PushToken::create(['user_id' => $user->id, 'token' => self::TOKEN]);
            app(ParentAccessService::class)->revoke(ParentProfile::where('user_id', $user->id)->first());
        });

        $this->assertSame(0, $this->inSchool(fn () => PushToken::count()));
    }
}
