<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Documentation Swagger de l'API parents. */
class ApiDocsTest extends TestCase
{
    use RefreshDatabase;

    public function test_swagger_page_and_openapi_spec_are_served(): void
    {
        $this->get('/api/docs')->assertOk()->assertSee('swagger-ui', false);

        $spec = $this->get('/api/docs/parent.yaml')->assertOk()->getContent();
        $this->assertStringContainsString('openapi: 3.0.3', $spec);
        $this->assertStringContainsString('/parent/login:', $spec);
    }

    public function test_every_documented_parent_route_exists(): void
    {
        $spec = file_get_contents(resource_path('openapi/parent.yaml'));
        preg_match_all('#^  (/[^:\s]+):#m', $spec, $matches);
        $documented = collect($matches[1])->map(fn ($p) => 'api/v1'.preg_replace('#\{[^}]+\}#', '{x}', $p))->unique();

        $routes = collect(app('router')->getRoutes()->getRoutes())->map(fn ($r) => preg_replace('#\{[^}/]+\??\}#', '{x}', $r->uri()))->unique();

        foreach ($documented as $path) {
            $this->assertTrue($routes->contains($path), "Route documentée introuvable : {$path}");
        }
    }
}
