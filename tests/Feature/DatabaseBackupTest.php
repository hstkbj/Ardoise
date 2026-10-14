<?php

namespace Tests\Feature;

use App\Models\Central\PlatformAdmin;
use App\Notifications\Platform\PlatformAlert;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithTenantSchool;
use Tests\TestCase;

/** Sauvegarde automatique des bases des écoles. */
class DatabaseBackupTest extends TestCase
{
    use InteractsWithTenantSchool, RefreshDatabase;

    protected function tearDown(): void
    {
        $this->tearDownSchool();
        parent::tearDown();
    }

    public function test_each_school_database_is_saved_compressed_and_old_backups_are_pruned(): void
    {
        $this->freezeTime();
        Storage::fake('backups');
        config(['ardoise.backup.disk' => 'backups', 'ardoise.backup.keep_days' => 14]);
        $tenant = $this->createSchool();
        Storage::disk('backups')->put('backups/'.now()->subDays(20)->format('Y-m-d_His').'/central.sqlite.gz', 'ancien');

        $this->artisan('backup:run')->assertSuccessful();

        $path = 'backups/'.now()->format('Y-m-d_His').'/tenant_'.$tenant->code.'.sqlite.gz';
        Storage::disk('backups')->assertExists($path);
        $this->assertStringStartsWith('SQLite format 3', gzdecode(Storage::disk('backups')->get($path)));
        $this->assertCount(1, Storage::disk('backups')->directories('backups'));
    }

    public function test_a_failed_backup_alerts_the_superadmins(): void
    {
        Notification::fake();
        Storage::fake('backups');
        config(['ardoise.backup.disk' => 'backups']);
        $admin = PlatformAdmin::create(['name' => 'Plateforme', 'email' => 'root@ardoise.test', 'password' => 'platform-password']);
        $tenant = $this->createSchool();
        config(['tenancy.database.sqlite_path' => '/nonexistent']);
        $this->app->forgetInstance(TenantManager::class);

        $this->artisan('backup:run', ['--tenant' => [$tenant->code]])->assertFailed();
        config(['tenancy.database.sqlite_path' => database_path('tenants')]);

        Notification::assertSentTo($admin, PlatformAlert::class, fn (PlatformAlert $alert) => $alert->event === 'backup.failed' && str_contains($alert->body, $tenant->code));
    }
}
