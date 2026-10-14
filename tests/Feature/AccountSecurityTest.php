<?php

namespace Tests\Feature;

use App\Models\Central\PlatformAdmin;
use App\Models\Central\PlatformSetting;
use App\Models\Tenant\User;
use App\Support\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenantSchool;
use Tests\TestCase;

/** Mot de passe provisoire et double authentification (TOTP). */
class AccountSecurityTest extends TestCase
{
    use InteractsWithTenantSchool, RefreshDatabase;

    protected function tearDown(): void
    {
        $this->tearDownSchool();
        parent::tearDown();
    }

    public function test_a_generated_admin_password_must_be_changed_at_first_login(): void
    {
        $this->createSchool(['admin_password' => null]);

        $this->assertTrue($this->inSchool(fn () => User::where('email', 'admin@ecole.test')->value('must_change_password')));
    }

    public function test_a_provisional_password_blocks_the_app_until_it_is_changed(): void
    {
        $this->createSchool();
        $this->inSchool(fn () => User::where('email', 'admin@ecole.test')->update(['must_change_password' => true]));
        $token = $this->tokenFor('school_admin', 'admin@ecole.test');

        $this->withToken($token)->getJson('/api/v1/options')
            ->assertForbidden()
            ->assertJsonPath('code', 'password_change_required');
        $this->withToken($token)->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.security.pending_action', 'password_change_required');

        $this->withToken($token)->putJson('/api/v1/profile/password', ['current' => 'admin-password', 'password' => 'admin-password', 'password_confirmation' => 'admin-password'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');

        $this->withToken($token)->putJson('/api/v1/profile/password', ['current' => 'admin-password', 'password' => 'Nouveau-mot-2026', 'password_confirmation' => 'Nouveau-mot-2026'])
            ->assertOk()
            ->assertJsonPath('data.security.must_change_password', false);

        $this->withToken($token)->getJson('/api/v1/options')->assertOk();
    }

    public function test_a_staff_member_enables_two_factor_and_then_needs_a_code_to_log_in(): void
    {
        $this->freezeTime();
        $tenant = $this->createSchool();
        $token = $this->tokenFor('school_admin', 'admin@ecole.test');

        $secret = str_replace(' ', '', $this->withToken($token)->postJson('/api/v1/profile/two-factor/setup')
            ->assertOk()
            ->assertJsonPath('data.otpauth_url', fn ($url) => str_starts_with($url, 'otpauth://totp/'))
            ->json('data.secret'));

        $this->withToken($token)->postJson('/api/v1/profile/two-factor/confirm', ['code' => '000000'])->assertUnprocessable();
        $codes = $this->withToken($token)->postJson('/api/v1/profile/two-factor/confirm', ['code' => Totp::code($secret)])
            ->assertOk()
            ->assertJsonPath('data.two_factor_enabled', true)
            ->assertJsonCount(8, 'data.recovery_codes')
            ->json('data.recovery_codes');

        $this->travel(1)->minutes();
        $login = ['login' => 'admin@ecole.test', 'password' => 'admin-password', 'school_code' => $tenant->code];
        $challenge = $this->postJson('/api/v1/auth/login', $login)
            ->assertOk()
            ->assertJsonPath('two_factor', true)
            ->assertJsonMissingPath('token')
            ->json('challenge');

        $this->postJson('/api/v1/auth/two-factor', ['challenge' => $challenge, 'code' => '123456'])->assertUnprocessable();
        $this->postJson('/api/v1/auth/two-factor', ['challenge' => $challenge, 'code' => Totp::code($secret)])
            ->assertOk()
            ->assertJsonPath('token', fn ($value) => str_starts_with($value, 't_'.$tenant->code.'.'));

        // Le même code ne peut pas resservir ; un code de secours ne sert qu'une fois
        $challenge = $this->postJson('/api/v1/auth/login', $login)->json('challenge');
        $this->postJson('/api/v1/auth/two-factor', ['challenge' => $challenge, 'code' => Totp::code($secret)])->assertUnprocessable();
        $this->postJson('/api/v1/auth/two-factor', ['challenge' => $challenge, 'recovery_code' => strtolower($codes[0])])->assertOk();

        $challenge = $this->postJson('/api/v1/auth/login', $login)->json('challenge');
        $this->postJson('/api/v1/auth/two-factor', ['challenge' => $challenge, 'recovery_code' => $codes[0]])->assertUnprocessable();
    }

    public function test_a_login_challenge_is_destroyed_after_five_wrong_codes(): void
    {
        $tenant = $this->createSchool();
        $this->inSchool(function () {
            $admin = User::where('email', 'admin@ecole.test')->first();
            $admin->startTwoFactorSetup();
            $admin->confirmTwoFactor();
        });

        $challenge = $this->postJson('/api/v1/auth/login', ['login' => 'admin@ecole.test', 'password' => 'admin-password', 'school_code' => $tenant->code])->json('challenge');

        foreach (range(1, 5) as $attempt) {
            $this->postJson('/api/v1/auth/two-factor', ['challenge' => $challenge, 'code' => '000000'])->assertUnprocessable();
        }

        $secret = $this->inSchool(fn () => User::where('email', 'admin@ecole.test')->first()->two_factor_secret);
        $this->postJson('/api/v1/auth/two-factor', ['challenge' => $challenge, 'code' => Totp::code($secret)])
            ->assertUnprocessable()
            ->assertJsonPath('errors.code.0', 'Session de connexion expirée. Saisissez à nouveau votre mot de passe.');
    }

    public function test_the_platform_can_require_two_factor_for_school_admins_only(): void
    {
        $this->createSchool();
        PlatformSetting::create(['section' => 'security', 'values' => ['require_2fa_school_admins' => 'yes']]);

        $adminToken = $this->tokenFor('school_admin', 'admin@ecole.test');
        $this->withToken($adminToken)->getJson('/api/v1/options')
            ->assertForbidden()
            ->assertJsonPath('code', 'two_factor_setup_required');
        $this->withToken($adminToken)->postJson('/api/v1/profile/two-factor/setup')->assertOk();

        $this->app['auth']->forgetGuards();
        $this->withToken($this->tokenFor('secretary'))->getJson('/api/v1/options')->assertOk();
    }

    public function test_two_factor_cannot_be_disabled_without_the_password_or_when_required(): void
    {
        $this->createSchool();
        $token = $this->tokenFor('school_admin', 'admin@ecole.test');
        $this->inSchool(function () {
            $admin = User::where('email', 'admin@ecole.test')->first();
            $admin->startTwoFactorSetup();
            $admin->confirmTwoFactor();
        });

        $this->withToken($token)->deleteJson('/api/v1/profile/two-factor', ['password' => 'wrong'])->assertUnprocessable();

        PlatformSetting::create(['section' => 'security', 'values' => ['require_2fa_school_admins' => 'yes']]);
        $this->withToken($token)->deleteJson('/api/v1/profile/two-factor', ['password' => 'admin-password'])->assertUnprocessable();

        PlatformSetting::where('section', 'security')->delete();
        $this->withToken($token)->deleteJson('/api/v1/profile/two-factor', ['password' => 'admin-password'])
            ->assertOk()
            ->assertJsonPath('data.two_factor_enabled', false);
    }

    public function test_a_superadmin_with_two_factor_logs_in_in_two_steps(): void
    {
        $admin = PlatformAdmin::create(['name' => 'Plateforme', 'email' => 'root@ardoise.test', 'password' => 'platform-password']);
        $secret = $admin->startTwoFactorSetup();
        $admin->confirmTwoFactor();

        $challenge = $this->withHeader('Referer', 'http://localhost')
            ->postJson('/api/v1/platform/auth/login', ['login' => 'root@ardoise.test', 'password' => 'platform-password'])
            ->assertOk()
            ->assertJsonPath('two_factor', true)
            ->assertJsonMissingPath('data')
            ->json('challenge');
        $this->assertGuest('platform');

        $this->withHeader('Referer', 'http://localhost')
            ->postJson('/api/v1/platform/auth/two-factor', ['challenge' => $challenge, 'code' => Totp::code($secret)])
            ->assertOk()
            ->assertJsonPath('data.security.two_factor_enabled', true);
        $this->assertAuthenticatedAs($admin, 'platform');
    }

    public function test_the_totp_codes_match_the_rfc_6238_reference_values(): void
    {
        // Secret de référence « 12345678901234567890 » (RFC 6238, annexe B, SHA-1, 8 derniers chiffres → 6)
        $secret = Totp::base32Encode('12345678901234567890');

        $this->assertSame('287082', Totp::code($secret, intdiv(59, 30)));
        $this->assertSame('081804', Totp::code($secret, intdiv(1111111109, 30)));
        $this->assertSame('005924', Totp::code($secret, intdiv(1234567890, 30)));
    }
}
