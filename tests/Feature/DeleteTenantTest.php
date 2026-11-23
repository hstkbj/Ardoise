<?php

namespace Tests\Feature;

use App\Models\Central\Domain;
use App\Models\Central\PlatformAdmin;
use App\Models\Central\Tenant;
use App\Tenancy\TenantManager;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeleteTenantTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_deletes_a_tenant_with_its_database_and_domain(): void
    {
        $this->seed(DatabaseSeeder::class);
        $platformAdmin = PlatformAdmin::create([
            'name' => 'Platform Test',
            'email' => 'platform-delete@example.test',
            'password' => 'platform-test-password',
        ]);

        $created = $this->actingAs($platformAdmin, 'platform')->postJson('/api/v1/platform/tenants', [
            'name' => 'Ecole a supprimer',
            'admin_name' => 'Admin Test',
            'admin_email' => 'admin-delete@example.test',
            'plan' => 'Essentiel',
            'status' => 'active',
        ])->assertCreated();

        $tenant = Tenant::findOrFail($created->json('data.id'));
        $path = app(TenantManager::class)->databasePath($tenant);
        $this->assertFileExists($path);

        $this->deleteJson('/api/v1/platform/tenants/'.$tenant->id)->assertOk();

        $this->assertFileDoesNotExist($path);
        $this->assertModelMissing($tenant);
        $this->assertSame(0, Domain::where('tenant_id', $tenant->id)->count());
    }

    public function test_deleting_a_tenant_requires_a_platform_admin(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->deleteJson('/api/v1/platform/tenants/1')->assertUnauthorized();
    }
}
