<?php

namespace Tests\Feature;

use App\Models\Tenant\Setting;
use App\Models\Tenant\Student;
use App\Models\Tenant\Term;
use App\Services\ReportCardService;
use App\Support\ReportCardHeader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithTenantSchool;
use Tests\TestCase;

/** En-tête du bulletin réglé par l'école : lignes officielles, logos, photo, signature et cachet. */
class ReportCardHeaderTest extends TestCase
{
    use InteractsWithTenantSchool, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    protected function tearDown(): void
    {
        $this->tearDownSchool();
        parent::tearDown();
    }

    public function test_the_school_header_settings_are_printed_on_the_report_card(): void
    {
        $this->createSchool();
        $ids = $this->seedSchoolData();
        $admin = $this->tokenFor('school_admin');

        $this->withToken($admin)->post('/api/v1/settings/identity', ['_method' => 'PUT', 'name' => 'Collège Les Palmiers', 'motto' => 'Travail – Discipline', 'logo' => UploadedFile::fake()->image('logo.png', 120, 120)], ['Accept' => 'application/json'])->assertOk();
        $this->withToken($admin)->putJson('/api/v1/settings/contact', ['address' => 'Cotonou, Akpakpa', 'phone' => '+229 21 00 00 00'])->assertOk();
        $this->withToken($admin)->post('/api/v1/settings/reports', [
            '_method' => 'PUT',
            'header_lines' => "République du Bénin\nMinistère des Enseignements Secondaire\n\n",
            'header_title' => 'Bulletin trimestriel',
            'header_show' => ['motto', 'address'],
            'signatory_name' => 'M. Koffi ADJOVI',
            'second_logo' => UploadedFile::fake()->image('armoiries.png', 80, 80),
            'signature' => UploadedFile::fake()->image('signature.png', 200, 60),
            'stamp' => UploadedFile::fake()->image('cachet.png', 90, 90),
        ], ['Accept' => 'application/json'])->assertOk();
        $this->inSchool(fn () => Student::find($ids['student_id'])->update(['photo_path' => UploadedFile::fake()->image('awa.jpg', 120, 150)->store('students', 'public')]));

        $header = $this->inSchool(fn () => ReportCardHeader::for(Student::find($ids['student_id'])));

        $this->assertSame(['République du Bénin', 'Ministère des Enseignements Secondaire'], $header['lines']);
        $this->assertSame(['Travail – Discipline', 'Cotonou, Akpakpa'], $header['details']);
        $this->assertSame('Bulletin trimestriel', $header['title']);
        foreach (['logo', 'second_logo', 'signature', 'stamp', 'photo'] as $image) {
            $this->assertStringStartsWith('data:image/', (string) $header[$image], "Image absente : {$image}");
        }

        $html = $this->inSchool(fn () => view('pdf.report-card', [
            'report' => app(ReportCardService::class)->present(Student::find($ids['student_id']), Term::current()->load('academicYear')),
            'header' => $header,
        ])->render());
        $this->assertStringContainsString('République du Bénin', $html);
        $this->assertStringContainsString('Bulletin trimestriel', $html);
        $this->assertStringContainsString('M. Koffi ADJOVI', $html);
        $this->assertSame(5, substr_count($html, 'src="data:image/'));

        $this->withToken($admin)->get("/api/v1/report-cards/{$ids['student_id']}/pdf")->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_images_can_be_removed_and_the_logo_hidden(): void
    {
        $this->createSchool();
        $this->seedSchoolData();
        $admin = $this->tokenFor('school_admin');
        $this->withToken($admin)->post('/api/v1/settings/reports', ['_method' => 'PUT', 'stamp' => UploadedFile::fake()->image('cachet.png')], ['Accept' => 'application/json'])->assertOk();

        $this->withToken($admin)->putJson('/api/v1/settings/reports', ['remove_files' => ['stamp'], 'header_logo' => 'none', 'show_student_photo' => 'no'])
            ->assertOk()
            ->assertJsonPath('data.stamp', null)
            ->assertJsonMissingPath('data.remove_files');

        $header = $this->inSchool(fn () => ReportCardHeader::for(null));
        $this->assertNull($header['stamp']);
        $this->assertNull($header['logo']);
        $this->assertFalse($header['show_photo']);

        $this->withToken($admin)->putJson('/api/v1/settings/reports', ['remove_files' => ['logo']])->assertJsonValidationErrors('remove_files.0');
    }

    public function test_the_preview_is_for_staff_who_can_see_the_settings(): void
    {
        $this->createSchool();
        $this->seedSchoolData();
        $this->inSchool(fn () => Setting::put('reports', ['header_lines' => 'République du Bénin']));

        $this->withToken($this->tokenFor('school_admin'))->get('/api/v1/settings/reports/preview')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->app['auth']->forgetGuards();
        $this->withToken($this->tokenFor('teacher'))->getJson('/api/v1/settings/reports/preview')->assertForbidden();
    }
}
