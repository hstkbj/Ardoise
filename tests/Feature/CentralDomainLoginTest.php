<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenantSchool;
use Tests\TestCase;

/** Domaine central : superadmin seulement. Une école se connecte depuis sa propre adresse. */
class CentralDomainLoginTest extends TestCase
{
    use InteractsWithTenantSchool, RefreshDatabase;

    protected function tearDown(): void
    {
        $this->tearDownSchool();
        parent::tearDown();
    }

    public function test_a_school_account_cannot_log_in_from_the_central_domain(): void
    {
        $this->createSchool();
        $credentials = ['login' => 'admin@ecole.test', 'password' => 'admin-password'];

        $this->postJson('/api/v1/auth/login', $credentials)->assertUnprocessable()->assertJsonValidationErrors('login');
        // Plus de « code établissement » : il est ignoré
        $this->postJson('/api/v1/auth/login', $credentials + ['school_code' => $this->tenant->code])->assertUnprocessable();
    }

    public function test_a_school_account_logs_in_from_its_own_address(): void
    {
        $this->createSchool();

        $this->postJson($this->schoolUrl('/api/v1/auth/login'), ['login' => 'admin@ecole.test', 'password' => 'admin-password'])
            ->assertOk()
            ->assertJsonStructure(['token']);
    }

    public function test_the_login_page_knows_where_it_is(): void
    {
        $this->createSchool();

        $this->getJson('/api/v1/public/context')
            ->assertOk()->assertJsonPath('data.central', true)->assertJsonPath('data.school', null);

        $this->getJson($this->schoolUrl('/api/v1/public/context'))
            ->assertOk()->assertJsonPath('data.central', false)->assertJsonPath('data.school.code', $this->tenant->code);
    }

    public function test_a_school_session_is_never_opened_on_the_central_domain(): void
    {
        $this->createSchool();
        $token = $this->tokenFor('school_admin');

        // Le jeton préfixé (application mobile) reste utilisable ; une session sans jeton ne désigne aucune école
        $this->as($token)->getJson('/api/v1/auth/me')->assertOk();
        $this->as('')->getJson('/api/v1/auth/me')->assertStatus(404);
    }
}
