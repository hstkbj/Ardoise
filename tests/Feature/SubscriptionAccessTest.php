<?php

namespace Tests\Feature;

use App\Models\Central\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenantSchool;
use Tests\TestCase;

/** Accès à l'école selon l'état de l'abonnement et le plan souscrit. */
class SubscriptionAccessTest extends TestCase
{
    use InteractsWithTenantSchool, RefreshDatabase;

    protected function tearDown(): void
    {
        $this->tearDownSchool();
        parent::tearDown();
    }

    public function test_during_the_grace_period_everyone_keeps_access_and_sees_the_state(): void
    {
        $this->freezeTime();
        $this->createSchool()->update(['expires_at' => now()->subDays(3)]);

        $this->withToken($this->tokenFor('secretary'))->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.subscription.state', 'grace')
            ->assertJsonPath('data.subscription.requires_payment', false);
    }

    public function test_after_the_grace_period_staff_are_blocked_with_402(): void
    {
        $this->freezeTime();
        $this->createSchool()->update(['expires_at' => now()->subDays(config('tenancy.grace_days') + 1)]);

        $this->withToken($this->tokenFor('secretary'))->getJson('/api/v1/options')
            ->assertStatus(402)
            ->assertJsonPath('code', 'subscription_expired');
    }

    public function test_after_the_grace_period_the_admin_can_only_reach_the_billing_pages(): void
    {
        $this->freezeTime();
        $this->createSchool()->update(['expires_at' => now()->subDays(30)]);
        $token = $this->tokenFor('school_admin');

        $this->withToken($token)->getJson('/api/v1/options')->assertStatus(402);
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.subscription.requires_payment', true);
        $this->withToken($token)->getJson('/api/v1/billing')->assertOk()->assertJsonPath('data.subscription.state', 'expired');
    }

    public function test_after_the_grace_period_only_the_admin_can_log_in(): void
    {
        $this->freezeTime();
        $tenant = $this->createSchool();
        $tenant->update(['expires_at' => now()->subDays(30)]);
        $this->tokenFor('secretary', 'secretariat@ecole.test');

        $this->postJson('/api/v1/auth/login', ['login' => 'secretariat@ecole.test', 'password' => 'password', 'school_code' => $tenant->code])
            ->assertStatus(402)
            ->assertJsonPath('code', 'subscription_expired');

        $this->postJson('/api/v1/auth/login', ['login' => 'admin@ecole.test', 'password' => 'admin-password', 'school_code' => $tenant->code])
            ->assertOk();
    }

    public function test_parents_cannot_log_in_once_the_subscription_has_expired(): void
    {
        $this->freezeTime();
        $tenant = $this->createSchool();
        $code = $this->seedSchoolData()['parent_code'];
        $tenant->update(['expires_at' => now()->subDays(30)]);

        $this->postJson('/api/v1/parent/login', ['code' => $code])->assertStatus(423);
    }

    public function test_a_suspended_school_refuses_even_the_admin(): void
    {
        $tenant = $this->createSchool(['status' => 'active']);
        $tenant->update(['status' => 'suspended']);

        $this->postJson('/api/v1/auth/login', ['login' => 'admin@ecole.test', 'password' => 'admin-password', 'school_code' => $tenant->code])
            ->assertStatus(423);
    }

    public function test_a_module_outside_the_plan_is_refused_and_hidden(): void
    {
        $this->createSchool(['plan_id' => Plan::where('name', 'Essentiel')->value('id')]);
        $token = $this->tokenFor('school_admin');

        $this->withToken($token)->getJson('/api/v1/fees')
            ->assertForbidden()
            ->assertJsonPath('code', 'feature_unavailable')
            ->assertJsonPath('feature', 'finance');

        $this->withToken($token)->getJson('/api/v1/assessments')->assertOk();

        $features = $this->withToken($token)->getJson('/api/v1/auth/me')->json('data.features');
        $this->assertContains('grades', $features);
        $this->assertNotContains('finance', $features);
    }

    public function test_parents_cannot_log_in_when_the_plan_has_no_parent_portal(): void
    {
        $plan = Plan::create(['name' => 'Minimal', 'period' => 'monthly', 'price' => 10000, 'features' => ['grades'], 'status' => 'active']);
        $this->createSchool(['plan_id' => $plan->id]);
        $code = $this->seedSchoolData()['parent_code'];

        $this->postJson('/api/v1/parent/login', ['code' => $code])->assertForbidden();
    }

    public function test_the_student_limit_of_the_plan_is_enforced(): void
    {
        $plan = Plan::create(['name' => 'Petit', 'period' => 'monthly', 'price' => 10000, 'max_students' => 1, 'features' => ['grades'], 'status' => 'active']);
        $this->createSchool(['plan_id' => $plan->id]);
        $classId = $this->seedSchoolData()['class_id'];

        $this->withToken($this->tokenFor('school_admin'))
            ->postJson('/api/v1/students', ['first_name' => 'Kofi', 'last_name' => 'TRAORÉ', 'class_id' => $classId])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['first_name' => 'Limite de votre plan atteinte : 1 élève(s) actif(s).']);
    }
}
