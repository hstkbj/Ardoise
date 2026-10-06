<?php

namespace Tests\Feature;

use App\Mail\TenantWelcomeMail;
use App\Models\Central\PlatformAdmin;
use App\Models\Central\Tenant;
use App\Tenancy\DatabaseCreator;
use App\Tenancy\TenantManager;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Identifiants envoyés à l'administrateur d'une école créée par la plateforme. */
class TenantCredentialsMailTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        app(TenantManager::class)->disconnect();
        Tenant::all()->each(fn (Tenant $tenant) => app(DatabaseCreator::class)->drop($tenant));
        parent::tearDown();
    }

    protected function platformAdmin(): PlatformAdmin
    {
        $this->seed(DatabaseSeeder::class);

        return PlatformAdmin::create(['name' => 'Plateforme', 'email' => 'ops@ardoise.test', 'password' => 'secret-password']);
    }

    public function test_creating_a_school_queues_the_credentials_to_its_admin(): void
    {
        Mail::fake();
        config(['app.url' => 'https://ardoise.test', 'tenancy.base_domain' => 'ardoise.test']);

        $response = $this->actingAs($this->platformAdmin(), 'platform')->postJson('/api/v1/platform/tenants', [
            'name' => 'Lycée Lumière',
            'admin_name' => 'Koffi Mensah',
            'admin_email' => 'directeur@lumiere.bj',
            'plan' => 'Essentiel',
        ])->assertCreated();

        $password = $response->json('meta.admin_password');

        Mail::assertQueued(TenantWelcomeMail::class, function (TenantWelcomeMail $mail) use ($password) {
            $html = $mail->render();

            return $mail->hasTo('directeur@lumiere.bj')
                && ! $mail->isReset
                && str_contains($html, 'https://lycee-lumiere.ardoise.test/login')
                && str_contains($html, $password);
        });
    }

    public function test_resending_credentials_replaces_the_admin_password(): void
    {
        Mail::fake();
        $admin = $this->platformAdmin();
        $created = $this->actingAs($admin, 'platform')->postJson('/api/v1/platform/tenants', [
            'name' => 'Collège Étoile', 'admin_name' => 'Ada Admin', 'admin_email' => 'ada@etoile.bj', 'plan' => 'Essentiel',
        ])->assertCreated();
        $oldPassword = $created->json('meta.admin_password');
        $tenant = Tenant::findOrFail($created->json('data.id'));

        $this->postJson("/api/v1/platform/tenants/{$tenant->id}/resend-credentials")->assertOk();

        $newPassword = null;
        Mail::assertQueued(TenantWelcomeMail::class, function (TenantWelcomeMail $mail) use (&$newPassword) {
            $newPassword = $mail->password;

            return $mail->isReset && $mail->hasTo('ada@etoile.bj');
        });

        $this->assertNotSame($oldPassword, $newPassword);
        Auth::shouldUse('web');
        $login = fn (string $password) => $this->postJson('/api/v1/auth/login', ['login' => 'ada@etoile.bj', 'password' => $password, 'school_code' => $tenant->code]);
        $login($oldPassword)->assertUnprocessable();
        $login($newPassword)->assertOk();
    }
}
