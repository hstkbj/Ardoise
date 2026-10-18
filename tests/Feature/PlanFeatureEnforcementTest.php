<?php

namespace Tests\Feature;

use App\Models\Central\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenantSchool;
use Tests\TestCase;

/** L'école ne peut faire que ce que son plan inclut : modules, tableaux de bord, réglages, limites. */
class PlanFeatureEnforcementTest extends TestCase
{
    use InteractsWithTenantSchool, RefreshDatabase;

    protected function tearDown(): void
    {
        $this->tearDownSchool();
        parent::tearDown();
    }

    protected function schoolWith(array $features, array $limits = []): Plan
    {
        $plan = Plan::create(['name' => 'Test', 'period' => 'monthly', 'price' => 10000, 'features' => $features, 'status' => 'active'] + $limits);
        $this->createSchool(['plan_id' => $plan->id]);

        return $plan;
    }

    public function test_the_dashboard_only_computes_modules_of_the_plan(): void
    {
        $this->schoolWith(['grades']);
        $this->seedSchoolData();

        $data = $this->withToken($this->tokenFor('school_admin'))->getJson('/api/v1/dashboard/school')->assertOk()->json('data');

        $this->assertSame(0, $data['finance']['expected']);
        $this->assertSame(0, $data['attendance_today']['absent']);
        $this->assertSame(0, $data['kpis']['parents']);
        $this->assertFalse($data['modules']['finance']);
        $this->assertTrue($data['modules']['grades']);
    }

    public function test_options_hide_fees_without_finance(): void
    {
        $this->schoolWith(['grades']);
        $this->seedSchoolData();

        $this->withToken($this->tokenFor('school_admin'))->getJson('/api/v1/options')
            ->assertOk()->assertJsonPath('data.fees', []);
    }

    public function test_setting_sections_follow_the_plan(): void
    {
        $this->schoolWith(['attendance']);
        $token = $this->tokenFor('school_admin');

        $this->withToken($token)->getJson('/api/v1/settings/finance')->assertForbidden();
        $this->withToken($token)->getJson('/api/v1/settings/grading')->assertForbidden();
        $this->withToken($token)->getJson('/api/v1/settings/online_payments')->assertForbidden();
        $this->withToken($token)->putJson('/api/v1/settings/finance', ['currency' => 'XOF'])->assertForbidden();
        $this->withToken($token)->getJson('/api/v1/settings/identity')->assertOk();
    }

    public function test_a_downgrade_closes_the_parent_portal_even_with_an_existing_token(): void
    {
        $plan = $this->schoolWith(['grades', 'attendance', 'parent_portal']);
        $parentToken = $this->codeToken($this->seedSchoolData()['parent_code']);

        $this->withToken($parentToken)->getJson('/api/v1/parent/children')->assertOk();

        $plan->update(['features' => ['grades']]);

        $this->withToken($parentToken)->getJson('/api/v1/parent/children')->assertForbidden();
        $this->withToken($parentToken)->getJson('/api/v1/notifications')->assertForbidden()->assertJsonPath('feature', 'parent_portal');
        $this->withToken($parentToken)->getJson('/api/v1/auth/me')->assertOk();
    }

    public function test_parent_document_requests_need_the_documents_module(): void
    {
        $this->schoolWith(['parent_portal', 'grades']);
        $parentToken = $this->codeToken($this->seedSchoolData()['parent_code']);

        $this->withToken($parentToken)->postJson('/api/v1/parent/document-requests', [])
            ->assertForbidden()->assertJsonPath('feature', 'documents');
    }

    public function test_reactivating_a_school_counts_in_the_plan_limit(): void
    {
        $this->schoolWith(['grades'], ['max_schools' => 1]);
        $token = $this->tokenFor('school_admin');
        $this->seedSchoolData(); // « Campus Centre » actif : la limite est atteinte

        $second = $this->inSchool(fn () => \App\Models\Tenant\Campus::create(['name' => 'Annexe', 'code' => 'AN', 'status' => 'inactive']));

        $this->withToken($token)->putJson("/api/v1/schools/{$second->id}", ['name' => 'Annexe', 'code' => 'AN', 'status' => 'active'])
            ->assertUnprocessable();

        $this->withToken($token)->putJson("/api/v1/schools/{$second->id}", ['name' => 'Annexe 2', 'code' => 'AN', 'status' => 'inactive'])
            ->assertOk();
    }
}
