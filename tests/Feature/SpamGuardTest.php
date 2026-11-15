<?php

namespace Tests\Feature;

use App\Support\SpamGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Anti-spam des formulaires publics : champ piège, jeton de temps, Turnstile. */
class SpamGuardTest extends TestCase
{
    use RefreshDatabase;

    private function demoPayload(array $extra = []): array
    {
        return $extra + [
            'name' => 'Directrice Test',
            'school' => 'École Démo',
            'email' => 'contact-demo@example.test',
            'phone' => '+2250700000000',
            'form_token' => $this->getJson('/api/v1/public/form-token')->json('data.token'),
        ];
    }

    public function test_a_valid_token_lets_the_demo_request_through(): void
    {
        $this->postJson('/api/v1/public/demo-requests', $this->demoPayload())->assertCreated();

        $this->assertDatabaseHas('demo_requests', ['email' => 'contact-demo@example.test'], 'central');
    }

    public function test_a_request_without_token_is_rejected(): void
    {
        $payload = $this->demoPayload();
        unset($payload['form_token']);

        $this->postJson('/api/v1/public/demo-requests', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('form_token');

        $this->assertDatabaseCount('demo_requests', 0, 'central');
    }

    public function test_a_tampered_token_is_rejected(): void
    {
        $this->postJson('/api/v1/public/demo-requests', $this->demoPayload(['form_token' => 'faux-jeton']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('form_token');
    }

    public function test_a_form_sent_too_fast_is_rejected(): void
    {
        config(['ardoise.spam.min_seconds' => 60]);

        $this->postJson('/api/v1/public/demo-requests', $this->demoPayload())
            ->assertUnprocessable()
            ->assertJsonPath('errors.form_token.0', 'Veuillez patienter un instant avant d’envoyer le formulaire.');

        $this->assertDatabaseCount('demo_requests', 0, 'central');
    }

    public function test_an_expired_token_is_rejected(): void
    {
        $token = SpamGuard::issueToken();
        $this->travel(7)->hours();

        $this->postJson('/api/v1/public/demo-requests', $this->demoPayload(['form_token' => $token]))
            ->assertUnprocessable();
    }

    public function test_the_honeypot_silently_drops_the_request(): void
    {
        $this->postJson('/api/v1/public/demo-requests', $this->demoPayload(['website' => 'http://spam.example']))
            ->assertCreated();

        $this->assertDatabaseCount('demo_requests', 0, 'central');
    }

    public function test_the_newsletter_is_protected_too(): void
    {
        $this->postJson('/api/v1/newsletter', ['email' => 'parent@example.test'])->assertUnprocessable();

        $this->postJson('/api/v1/newsletter', [
            'email' => 'parent@example.test',
            'form_token' => $this->getJson('/api/v1/public/form-token')->json('data.token'),
        ])->assertSuccessful();

        $this->postJson('/api/v1/newsletter', ['email' => 'robot@example.test', 'website' => 'x'])->assertSuccessful();
        $this->assertDatabaseMissing('newsletter_subscribers', ['email' => 'robot@example.test'], 'central');
    }

    public function test_the_guard_can_be_disabled(): void
    {
        config(['ardoise.spam.enabled' => false]);

        $this->postJson('/api/v1/public/demo-requests', [
            'name' => 'Directrice Test',
            'school' => 'École Démo',
            'email' => 'contact-demo@example.test',
            'phone' => '+2250700000000',
        ])->assertCreated();
    }

    public function test_turnstile_is_enforced_on_the_demo_request_when_configured(): void
    {
        config(['ardoise.spam.turnstile.site_key' => 'site-key', 'ardoise.spam.turnstile.secret_key' => 'secret-key']);
        Http::fake(['challenges.cloudflare.com/*' => Http::sequence()->push(['success' => false])->push(['success' => true])]);

        $this->postJson('/api/v1/public/demo-requests', $this->demoPayload())
            ->assertUnprocessable()
            ->assertJsonPath('errors.form_token.0', 'Veuillez confirmer que vous n’êtes pas un robot.');

        $this->postJson('/api/v1/public/demo-requests', $this->demoPayload(['cf-turnstile-response' => 'mauvais']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.form_token.0', 'La vérification anti-robot a échoué, réessayez.');

        $this->postJson('/api/v1/public/demo-requests', $this->demoPayload(['cf-turnstile-response' => 'bon']))
            ->assertCreated();

        Http::assertSent(fn ($request) => $request['secret'] === 'secret-key' && $request['response'] === 'bon');
    }

    public function test_the_turnstile_secret_never_reaches_the_page(): void
    {
        $this->withoutVite();
        config(['ardoise.spam.turnstile.site_key' => 'site-key-public', 'ardoise.spam.turnstile.secret_key' => 'secret-key-prive']);

        $this->get('/contact')
            ->assertSee('site-key-public', false)
            ->assertDontSee('secret-key-prive', false);
    }
}
