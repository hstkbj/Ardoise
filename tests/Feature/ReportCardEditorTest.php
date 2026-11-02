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

/** Éditeur de bulletin : style, en-tête, élève, logos et filigrane. Le tableau des notes ne change pas. */
class ReportCardEditorTest extends TestCase
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

    protected function render(array $ids): string
    {
        return $this->inSchool(fn () => view('pdf.report-card', [
            'report' => app(ReportCardService::class)->present(Student::find($ids['student_id']), Term::current()->load('academicYear')),
            'header' => ReportCardHeader::for(Student::find($ids['student_id'])),
        ])->render());
    }

    public function test_the_default_layout_is_unchanged(): void
    {
        $this->createSchool();
        $ids = $this->seedSchoolData();

        $html = $this->render($ids);

        $this->assertStringContainsString('band-band', $html);
        $this->assertStringNotContainsString('class="page-border"', $html);
        $this->assertStringNotContainsString('class="watermark', $html);
        $this->assertStringContainsString('Matricule', $html);
        $this->assertStringContainsString('Né(e) le', $html);
        $this->assertStringContainsString('<table class="grades">', $html);
    }

    public function test_the_editor_settings_are_saved_and_validated(): void
    {
        $this->createSchool();
        $this->seedSchoolData();
        $admin = $this->tokenFor('school_admin');

        $this->withToken($admin)->putJson('/api/v1/settings/reports', [
            'design_preset' => 'official', 'accent_color' => '#123456', 'font_family' => 'serif', 'page_border' => 'double', 'header_align' => 'left',
            'logo_size' => 'large', 'header_divider' => 'yes', 'title_style' => 'line', 'student_layout' => 'card', 'student_fields' => ['matricule', 'year'],
            'photo_position' => 'left', 'photo_size' => 'small', 'watermark_mode' => 'text', 'watermark_text' => 'COLLÈGE', 'watermark_opacity' => 12, 'watermark_size' => 'medium',
        ])->assertOk()->assertJsonPath('data.title_style', 'line')->assertJsonPath('data.watermark_opacity', 12);

        $this->withToken($admin)->putJson('/api/v1/settings/reports', ['title_style' => 'neon'])->assertJsonValidationErrors('title_style');
        $this->withToken($admin)->putJson('/api/v1/settings/reports', ['accent_color' => 'rouge'])->assertJsonValidationErrors('accent_color');
        $this->withToken($admin)->putJson('/api/v1/settings/reports', ['watermark_opacity' => 90])->assertJsonValidationErrors('watermark_opacity');
        $this->withToken($admin)->putJson('/api/v1/settings/reports', ['student_fields' => ['password']])->assertJsonValidationErrors('student_fields.0');
    }

    public function test_the_options_reach_the_pdf(): void
    {
        $this->createSchool();
        $ids = $this->seedSchoolData();
        $this->inSchool(fn () => Setting::put('reports', [
            'accent_color' => '#123456', 'font_family' => 'serif', 'page_border' => 'double', 'header_align' => 'left', 'logo_size' => 'large', 'header_divider' => 'yes',
            'title_style' => 'line', 'student_layout' => 'card', 'student_fields' => ['matricule'], 'photo_position' => 'left',
            'watermark_mode' => 'text', 'watermark_text' => 'COLLÈGE LES PALMIERS', 'watermark_opacity' => 12,
        ]));

        $header = $this->inSchool(fn () => ReportCardHeader::for(Student::find($ids['student_id'])));
        $this->assertSame('#123456', $header['color']);
        $this->assertSame(100, $header['logo_px']);
        $this->assertSame('left', $header['align']);
        $this->assertTrue($header['divider']);
        $this->assertSame(['matricule'], $header['student_fields']);
        $this->assertSame(['type' => 'text', 'src' => null, 'text' => 'COLLÈGE LES PALMIERS', 'opacity' => 0.12, 'width' => 70], $header['watermark']);

        $html = $this->render($ids);
        $this->assertStringContainsString('band-line', $html);
        $this->assertStringContainsString('class="page-border"', $html);
        $this->assertStringContainsString('hdr-divider', $html);
        $this->assertStringContainsString('who-card', $html);
        $this->assertStringContainsString('DejaVu Serif', $html);
        $this->assertStringContainsString('COLLÈGE LES PALMIERS', $html);
        $this->assertStringContainsString('Matricule', $html);
        $this->assertStringNotContainsString('Né(e) le', $html);
        $this->assertStringContainsString('<table class="grades">', $html);
    }

    public function test_the_school_logo_can_be_used_as_watermark(): void
    {
        $this->createSchool();
        $ids = $this->seedSchoolData();
        $admin = $this->tokenFor('school_admin');

        $this->withToken($admin)->post('/api/v1/settings/identity', ['_method' => 'PUT', 'name' => 'Collège Les Palmiers', 'logo' => UploadedFile::fake()->image('logo.png', 200, 200)], ['Accept' => 'application/json'])->assertOk();
        $this->withToken($admin)->putJson('/api/v1/settings/reports', ['watermark_mode' => 'logo', 'watermark_opacity' => 6])->assertOk();

        $html = $this->render($ids);
        $this->assertStringContainsString('class="watermark"', $html);
        $this->assertStringContainsString('opacity: 0.06', $html);
    }

    public function test_a_custom_watermark_image_can_be_uploaded_then_removed(): void
    {
        $this->createSchool();
        $ids = $this->seedSchoolData();
        $admin = $this->tokenFor('school_admin');

        $this->withToken($admin)->post('/api/v1/settings/reports', ['_method' => 'PUT', 'watermark_mode' => 'image', 'watermark_image' => UploadedFile::fake()->image('filigrane.png', 300, 300)], ['Accept' => 'application/json'])->assertOk();

        $header = $this->inSchool(fn () => ReportCardHeader::for(Student::find($ids['student_id'])));
        $this->assertSame('image', $header['watermark']['type']);
        $this->assertStringStartsWith('data:image/', $header['watermark']['src']);

        $this->withToken($admin)->putJson('/api/v1/settings/reports', ['remove_files' => ['watermark_image']])->assertOk()->assertJsonPath('data.watermark_image', null);
        $this->assertNull($this->inSchool(fn () => ReportCardHeader::for(null))['watermark']);
    }

    public function test_a_watermark_without_logo_or_text_prints_nothing(): void
    {
        $this->createSchool();
        $this->seedSchoolData();
        $this->inSchool(fn () => Setting::put('reports', ['watermark_mode' => 'logo']));

        $this->assertNull($this->inSchool(fn () => ReportCardHeader::for(null))['watermark']);

        $this->inSchool(fn () => Setting::put('reports', ['watermark_mode' => 'text', 'watermark_text' => '  ']));
        $this->assertNull($this->inSchool(fn () => ReportCardHeader::for(null))['watermark']);
    }
}
