<?php

namespace Tests\Feature;

use App\Models\Tenant\ActivityLog;
use App\Models\Tenant\Student;
use App\Models\Tenant\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenantSchool;
use Tests\TestCase;

/** Consultation du journal d'activité de l'école. */
class ActivityLogTest extends TestCase
{
    use InteractsWithTenantSchool, RefreshDatabase;

    protected function tearDown(): void
    {
        $this->tearDownSchool();
        parent::tearDown();
    }

    public function test_the_direction_reads_the_log_with_readable_labels_and_filters(): void
    {
        $this->createSchool();
        $this->seedSchoolData();
        $this->inSchool(function () {
            $this->actingAs(User::where('email', 'admin@ecole.test')->first());
            ActivityLog::record('student.archived', Student::first());
            ActivityLog::record('payment.recorded', Student::first(), ['amount' => 50000, 'fee' => 'Scolarité']);
        });
        $this->app['auth']->forgetGuards();

        $this->withToken($this->tokenFor('director'))->getJson('/api/v1/activity-log?filter[group]=student')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.action_label', 'Élève archivé')
            ->assertJsonPath('data.0.user_name', 'Awa Admin')
            ->assertJsonPath('data.0.subject_label', 'Élève : TRAORÉ Awa');

        $this->withToken($this->tokenFor('director'))->getJson('/api/v1/activity-log?filter[group]=payment')
            ->assertJsonPath('data.0.details', 'montant : 50000 · frais : Scolarité');
    }

    public function test_staff_without_the_permission_cannot_read_the_log(): void
    {
        $this->createSchool();

        $this->withToken($this->tokenFor('secretary'))->getJson('/api/v1/activity-log')->assertForbidden();
    }
}
