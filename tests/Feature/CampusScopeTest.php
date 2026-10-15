<?php

namespace Tests\Feature;

use App\Models\Tenant\Campus;
use App\Models\Tenant\ClassRoom;
use App\Models\Tenant\Level;
use App\Models\Tenant\Student;
use App\Models\Tenant\User;
use App\Services\EnrollmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithTenantSchool;
use Tests\TestCase;

/** Le personnel rattaché à un site ne voit que ce site ; l'administrateur voit tout. */
class CampusScopeTest extends TestCase
{
    use InteractsWithTenantSchool, RefreshDatabase;

    protected function tearDown(): void
    {
        $this->tearDownSchool();
        parent::tearDown();
    }

    /** @return array{site_a: int, site_b: int, student_a: int, student_b: int, class_a: int, class_b: int} */
    protected function twoSites(): array
    {
        $this->createSchool();
        $data = $this->seedSchoolData();

        return $this->inSchool(function () use ($data) {
            $a = ClassRoom::findOrFail($data['class_id']);
            $siteB = Campus::create(['name' => 'Campus Nord', 'code' => 'CN', 'status' => 'active']);
            $classB = ClassRoom::create(['name' => '5e B', 'level_id' => Level::where('name', '5e')->value('id'), 'campus_id' => $siteB->id, 'status' => 'active']);
            $studentB = Student::create(['matricule' => 'T-0200', 'first_name' => 'Yao', 'last_name' => 'KOUASSI', 'status' => 'active']);
            app(EnrollmentService::class)->enroll($studentB, $classB);

            return [
                'site_a' => $a->campus_id, 'site_b' => $siteB->id,
                'student_a' => $data['student_id'], 'student_b' => $studentB->id,
                'class_a' => $a->id, 'class_b' => $classB->id,
            ];
        });
    }

    protected function linkToSite(string $token, int $campusId, string $email = 'secretary@ecole.test'): void
    {
        $this->inSchool(fn () => DB::connection('tenant')->table('campus_user')->insert([
            'campus_id' => $campusId, 'user_id' => User::where('email', $email)->value('id'),
        ]));
    }

    public function test_staff_linked_to_a_site_only_sees_that_site(): void
    {
        $sites = $this->twoSites();
        $secretary = $this->tokenFor('secretary');
        $this->linkToSite($secretary, $sites['site_a']);
        $admin = $this->tokenFor('school_admin');

        $students = collect($this->as($secretary)->getJson('/api/v1/students')->assertOk()->json('data'))->pluck('id');
        $this->assertTrue($students->contains($sites['student_a']));
        $this->assertFalse($students->contains($sites['student_b']));

        $classes = collect($this->as($secretary)->getJson('/api/v1/classes')->assertOk()->json('data'))->pluck('id');
        $this->assertSame([$sites['class_a']], $classes->all());

        $this->as($secretary)->getJson("/api/v1/students/{$sites['student_b']}")->assertNotFound();
        $this->as($secretary)->getJson("/api/v1/classes/{$sites['class_b']}")->assertNotFound();

        $schools = collect($this->as($secretary)->getJson('/api/v1/options')->assertOk()->json('data.schools'))->pluck('value');
        $this->assertSame([$sites['site_a']], $schools->all());

        // L'administrateur voit les deux sites
        $this->assertCount(2, $this->as($admin)->getJson('/api/v1/students')->assertOk()->json('data'));
        $this->assertCount(2, $this->as($admin)->getJson('/api/v1/classes')->assertOk()->json('data'));
    }

    public function test_the_year_filter_stays_inside_the_site(): void
    {
        $sites = $this->twoSites();
        $secretary = $this->tokenFor('secretary');
        $this->linkToSite($secretary, $sites['site_b']);
        $year = $this->inSchool(fn () => \App\Models\Tenant\AcademicYear::current()->id);

        $ids = collect($this->as($secretary)->getJson("/api/v1/classes?filter[academic_year_id]={$year}")->assertOk()->json('data'))->pluck('id');
        $this->assertSame([$sites['class_b']], $ids->all());
    }

    public function test_staff_cannot_publish_for_another_site(): void
    {
        $sites = $this->twoSites();
        $director = $this->tokenFor('director', 'director@ecole.test');
        $this->linkToSite($director, $sites['site_a'], 'director@ecole.test');
        $note = ['title' => 'Réunion', 'body' => 'Réunion des parents', 'status' => 'draft'];

        $this->as($director)->postJson('/api/v1/announcements', $note + ['school_id' => $sites['site_b']])->assertForbidden();
        $this->as($director)->postJson('/api/v1/announcements', $note)->assertForbidden(); // « tous les sites » : administrateur seulement
        $this->as($director)->postJson('/api/v1/announcements', $note + ['school_id' => $sites['site_a']])->assertSuccessful();
    }

    public function test_staff_without_a_site_keep_seeing_everything(): void
    {
        $sites = $this->twoSites();
        $secretary = $this->tokenFor('secretary');

        $this->assertCount(2, $this->as($secretary)->getJson('/api/v1/students')->assertOk()->json('data'));
    }
}
