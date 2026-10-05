<?php

namespace Tests\Feature;

use App\Actions\CreateTenant;
use App\Models\Central\Tenant;
use App\Services\AccountService;
use App\Tenancy\DatabaseCreator;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Connexion d'un parent avec son seul code : le serveur retrouve l'école et le parent.
 * Crée une vraie base d'école (SQLite) puis la supprime.
 */
class ParentCodeLoginTest extends TestCase
{
    use RefreshDatabase;

    protected ?Tenant $tenant = null;
    protected string $adminPassword;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
        $this->adminPassword = 'tenant-test-password';
        $this->tenant = app(CreateTenant::class)->handle([
            'name' => 'École Test', 'code' => 'test-'.substr(uniqid(), -6),
            'admin_name' => 'Admin Test', 'admin_email' => 'admin@test.ci', 'admin_password' => $this->adminPassword, 'status' => 'active',
        ])['tenant'];
    }

    protected function tearDown(): void
    {
        if ($this->tenant) {
            app(TenantManager::class)->disconnect();
            app(DatabaseCreator::class)->drop($this->tenant);
        }
        parent::tearDown();
    }

    protected function issueParentCode(): string
    {
        return app(TenantManager::class)->run($this->tenant, function () {
            [, $code] = app(AccountService::class)->createParent(['first_name' => 'Mariam', 'last_name' => 'Traoré', 'phone' => '+2250700000001']);

            return $code;
        });
    }

    public function test_a_parent_logs_in_with_the_code_only_and_gets_a_tenant_prefixed_token(): void
    {
        $code = $this->issueParentCode();

        $response = $this->postJson('/api/v1/parent/login', ['code' => strtolower($code), 'device_name' => 'iPhone']);

        $response->assertOk()->assertJsonPath('token_type', 'Bearer');
        $token = $response->json('token');
        $this->assertStringStartsWith('t_'.$this->tenant->code.'.', $token);

        app(TenantManager::class)->disconnect();

        $this->withToken($token)->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.role', 'parent')
            ->assertJsonPath('data.tenant.code', $this->tenant->code);

        $this->withToken($token)->getJson('/api/v1/parent/children')->assertOk()->assertJsonPath('data', []);
    }

    public function test_an_unknown_code_is_rejected(): void
    {
        $this->postJson('/api/v1/parent/login', ['code' => 'ABCD-EFGH-JKMN'])->assertStatus(422)->assertJsonValidationErrors('code');
    }

    public function test_a_parent_cannot_reach_staff_routes(): void
    {
        $token = $this->postJson('/api/v1/parent/login', ['code' => $this->issueParentCode()])->json('token');
        app(TenantManager::class)->disconnect();

        $this->withToken($token)->getJson('/api/v1/students')->assertForbidden();
    }

    public function test_a_tenant_admin_can_log_in_and_reuse_the_session_on_tenant_routes(): void
    {
        config(['session.driver' => 'database']);
        $tenantHost = $this->tenant->code.'.localhost:8000';

        $this->withHeader('Origin', 'http://'.$tenantHost)
            ->postJson('http://'.$tenantHost.'/api/v1/auth/login', [
                'login' => 'admin@test.ci',
                'password' => $this->adminPassword,
            ])
            ->assertOk()
            ->assertJsonPath('data.tenant.code', $this->tenant->code);

        Auth::forgetGuards();

        $this->withHeader('Origin', 'http://'.$tenantHost)
            ->getJson('http://'.$tenantHost.'/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.tenant.code', $this->tenant->code);

        $this->withHeader('Origin', 'http://'.$tenantHost)
            ->getJson('http://'.$tenantHost.'/api/v1/options')
            ->assertOk();

        $this->withHeader('Host', $tenantHost)
            ->get('http://'.$tenantHost.'/admin/dashboard')
            ->assertOk();
    }

    public function test_a_regenerated_code_invalidates_the_old_one(): void
    {
        $old = $this->issueParentCode();

        app(TenantManager::class)->run($this->tenant, function () {
            $parent = \App\Models\Tenant\ParentProfile::first();
            app(\App\Services\ParentAccessService::class)->issue($parent);
        });

        $this->postJson('/api/v1/parent/login', ['code' => $old])->assertStatus(422);
    }
}
