<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** FORCE_HTTPS : redirection vers HTTPS et en-tête HSTS. */
class ForceHttpsTest extends TestCase
{
    use RefreshDatabase;

    public function test_http_is_redirected_to_https_when_forced(): void
    {
        config(['ardoise.force_https' => true]);

        $this->get('http://localhost/api/docs?lang=fr')
            ->assertStatus(301)
            ->assertRedirect('https://localhost/api/docs?lang=fr');
    }

    public function test_unsafe_methods_are_redirected_without_changing_method(): void
    {
        config(['ardoise.force_https' => true]);

        $this->post('http://localhost/api/v1/newsletter', ['email' => 'parent@example.com'])
            ->assertStatus(308)
            ->assertRedirect('https://localhost/api/v1/newsletter');
    }

    public function test_https_requests_receive_the_hsts_header(): void
    {
        config(['ardoise.force_https' => true]);

        $this->get('https://localhost/api/docs')
            ->assertOk()
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function test_health_check_stays_reachable_over_http(): void
    {
        config(['ardoise.force_https' => true]);

        $this->get('http://localhost/up')->assertOk();
    }

    public function test_nothing_changes_when_https_is_not_forced(): void
    {
        config(['ardoise.force_https' => false]);

        $this->get('http://localhost/api/docs')
            ->assertOk()
            ->assertHeaderMissing('Strict-Transport-Security');
    }
}
