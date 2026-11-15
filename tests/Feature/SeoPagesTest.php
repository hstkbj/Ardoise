<?php

namespace Tests\Feature;

use App\Support\SeoMeta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/** Balises meta, robots.txt et plan du site. */
class SeoPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_public_pages_have_their_own_title_description_and_social_preview(): void
    {
        foreach (config('ardoise.seo.pages') as $path => $page) {
            $this->get($path)
                ->assertOk()
                ->assertSee('<title>'.e($page['title']).'</title>', false)
                ->assertSee('<meta name="description" content="'.e($page['description']).'">', false)
                ->assertSee('<meta property="og:image" content="http://localhost/og-image.png">', false)
                ->assertSee('<meta name="twitter:card" content="summary_large_image">', false)
                ->assertSee('<meta name="robots" content="index, follow">', false);
        }
    }

    public function test_other_pages_are_not_indexed(): void
    {
        $this->get('/login')->assertOk()->assertSee('<meta name="robots" content="noindex, nofollow">', false);
        $this->get('/admin/dashboard')->assertOk()->assertSee('noindex, nofollow', false);
    }

    public function test_school_hosts_are_not_indexed_even_on_public_paths(): void
    {
        $meta = SeoMeta::forRequest(Request::create('http://palmiers.localhost/pricing'), false);

        $this->assertSame('noindex, nofollow', $meta['robots']);
    }

    public function test_the_social_preview_image_and_the_favicons_exist(): void
    {
        foreach ([ltrim(config('ardoise.seo.image'), '/'), 'favicon.ico', 'favicon.svg', 'apple-touch-icon.png', 'icon-192.png', 'icon-512.png', 'site.webmanifest'] as $file) {
            $this->assertGreaterThan(0, filesize(public_path($file)), "public/{$file} est vide ou absent.");
        }

        $this->get('/')
            ->assertSee('<link rel="icon" href="/favicon.svg" type="image/svg+xml">', false)
            ->assertSee('<link rel="apple-touch-icon" href="/apple-touch-icon.png">', false)
            ->assertSee('<link rel="manifest" href="/site.webmanifest">', false);
    }

    public function test_unknown_addresses_answer_404_while_known_ones_answer_200(): void
    {
        $this->get('/page-qui-n-existe-pas')->assertNotFound()->assertSee('noindex, nofollow', false);
        $this->get('/login')->assertOk();
        $this->get('/admin/students')->assertOk();
        $this->get('/parent/grades')->assertOk();
    }

    public function test_the_server_404_page_is_customised(): void
    {
        $this->get('/storage/fichier-absent.png')
            ->assertNotFound()
            ->assertSee('Page introuvable', false)
            ->assertSee('Revenir à l’accueil', false);
    }

    public function test_robots_and_sitemap_are_served(): void
    {
        $this->get('/robots.txt')
            ->assertOk()
            ->assertSee('Disallow: /admin', false)
            ->assertSee('Sitemap: http://localhost/sitemap.xml', false);

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee('<loc>http://localhost/privacy</loc>', false)
            ->assertSee('<loc>http://localhost/terms</loc>', false)
            ->assertDontSee('/admin', false);
    }

    public function test_school_hosts_get_a_closed_robots_file_and_no_sitemap(): void
    {
        $this->get('http://palmiers.localhost/robots.txt')
            ->assertOk()
            ->assertSee('Disallow: /', false)
            ->assertDontSee('Sitemap:', false);
    }

    public function test_analytics_is_exposed_only_when_configured_and_valid(): void
    {
        $this->withoutVite();

        $this->get('/')->assertSee('"analytics":null', false);

        config(['ardoise.analytics.provider' => 'plausible', 'ardoise.analytics.id' => 'ardoise.test', 'ardoise.analytics.src' => 'http://pas-https.test/s.js']);
        $this->get('/')
            ->assertSee('"analytics":{"provider":"plausible","id":"ardoise.test","src":null}', false);

        config(['ardoise.analytics.provider' => 'inconnu']);
        $this->get('/')->assertSee('"analytics":null', false);
    }

    public function test_legal_details_are_passed_to_the_page_without_secrets(): void
    {
        config(['ardoise.legal.company' => 'Ardoise SARL', 'ardoise.legal.email' => 'contact@ardoise.test']);

        $this->get('/privacy')
            ->assertSee('Ardoise SARL', false)
            ->assertSee('contact@ardoise.test', false)
            ->assertDontSee('FEDAPAY', false);
    }
}
