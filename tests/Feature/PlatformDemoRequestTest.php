<?php

namespace Tests\Feature;

use App\Models\Central\PlatformAdmin;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformDemoRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_demo_request_is_saved_and_visible_to_the_platform_admin(): void
    {
        $this->seed(DatabaseSeeder::class);
        $platformAdmin = PlatformAdmin::create([
            'name' => 'Platform Test',
            'email' => 'platform-test@example.test',
            'password' => 'platform-test-password',
        ]);
        $payload = [
            'name' => 'Directrice Test',
            'role' => 'Directrice',
            'school' => 'École Démo',
            'city' => 'Abidjan',
            'email' => 'contact-demo@example.test',
            'phone' => '+2250700000000',
            'students' => '300 à 1 000',
            'sites' => 2,
            'message' => 'Je souhaite une démonstration.',
        ];

        $this->postJson('/api/v1/public/demo-requests', $payload)
            ->assertCreated()
            ->assertJsonPath('message', 'Demande envoyée. Nous vous recontactons rapidement.');

        $this->assertDatabaseHas('demo_requests', ['email' => $payload['email']], 'central');

        $this->actingAs($platformAdmin, 'platform')
            ->getJson('/api/v1/platform/demo-requests?search=École%20Démo')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.school', $payload['school'])
            ->assertJsonPath('data.0.name', $payload['name'])
            ->assertJsonPath('data.0.email', $payload['email'])
            ->assertJsonPath('data.0.message', $payload['message']);
    }

    public function test_demo_requests_are_not_visible_without_platform_authentication(): void
    {
        $response = $this->getJson('/api/v1/platform/demo-requests');

        $response->assertUnauthorized();
    }
}
