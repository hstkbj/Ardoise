<?php

namespace Tests\Feature;

use App\Models\Central\PlatformAdmin;
use App\Models\Central\Tenant;
use App\Tenancy\DatabaseCreator;
use App\Tenancy\TenantManager;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateTenantTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_generates_a_unique_code_and_sqlite_database_from_tenant_name(): void
    {
        $this->seed(DatabaseSeeder::class);
        $platformAdmin = PlatformAdmin::create([
            'name' => 'Platform Test',
            'email' => 'platform-test@example.test',
            'password' => 'platform-test-password',
        ]);

        $payload = [
            'name' => 'Espérance 2000',
            'admin_name' => 'Admin Test',
            'admin_email' => 'admin-one@example.test',
            'plan' => 'Essentiel',
            'status' => 'active',
        ];

        try {
            $firstResponse = $this->actingAs($platformAdmin, 'platform')
                ->postJson('/api/v1/platform/tenants', $payload);

            $firstResponse->assertCreated()
                ->assertJsonPath('data.code', 'esperance-2000')
                ->assertJsonPath('data.domain', 'esperance-2000.localhost');

            $secondResponse = $this->postJson('/api/v1/platform/tenants', [
                ...$payload,
                'admin_email' => 'admin-two@example.test',
            ]);

            $secondResponse->assertCreated()
                ->assertJsonPath('data.code', 'esperance-2000-2')
                ->assertJsonPath('data.domain', 'esperance-2000-2.localhost');

            foreach ([$firstResponse, $secondResponse] as $response) {
                $tenant = Tenant::findOrFail($response->json('data.id'));
                $this->assertFileExists(app(TenantManager::class)->databasePath($tenant));
            }
        } finally {
            app(TenantManager::class)->disconnect();

            foreach (Tenant::whereIn('code', ['esperance-2000', 'esperance-2000-2'])->get() as $tenant) {
                app(DatabaseCreator::class)->drop($tenant);
            }
        }
    }
}
