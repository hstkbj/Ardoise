<?php

namespace Tests\Feature;

use App\Models\Tenant\AcademicYear;
use App\Models\Tenant\ClassRoom;
use App\Models\Tenant\Enrollment;
use App\Models\Tenant\Student;
use App\Services\EnrollmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\InteractsWithTenantSchool;
use Tests\TestCase;

/** Les classes sont créées une fois : seule l'année change, via les inscriptions. */
class ClassesAcrossYearsTest extends TestCase
{
    use InteractsWithTenantSchool, RefreshDatabase;

    protected function tearDown(): void
    {
        $this->tearDownSchool();
        parent::tearDown();
    }

    public function test_a_class_has_no_academic_year(): void
    {
        $this->createSchool();
        $data = $this->seedSchoolData();

        $this->inSchool(fn () => $this->assertFalse(Schema::connection('tenant')->hasColumn('class_rooms', 'academic_year_id')));

        $row = $this->withToken($this->tokenFor('school_admin'))->getJson("/api/v1/classes/{$data['class_id']}")->assertOk()->json('data');
        $this->assertArrayNotHasKey('academic_year_id', $row);
    }

    public function test_the_same_class_is_reused_when_a_new_year_starts(): void
    {
        $this->createSchool();
        $data = $this->seedSchoolData();

        $this->inSchool(function () use ($data) {
            $old = AcademicYear::current();
            $next = AcademicYear::create(['name' => '2099-2100', 'starts_on' => '2099-09-01', 'ends_on' => '2100-06-30', 'period_type' => 'trimester', 'status' => 'upcoming']);

            $old->update(['status' => 'closed']);
            $old->enrollments()->where('status', 'active')->update(['status' => 'completed']);
            $next->update(['status' => 'active']);

            $this->assertSame(1, ClassRoom::count());
            $this->assertSame(0, ClassRoom::findOrFail($data['class_id'])->students()->count());

            $student = Student::findOrFail($data['student_id']);
            app(EnrollmentService::class)->enroll($student, ClassRoom::findOrFail($data['class_id']));

            $this->assertSame(1, ClassRoom::findOrFail($data['class_id'])->students()->count());
            $this->assertSame([$old->id, $next->id], Enrollment::where('student_id', $student->id)->orderBy('id')->pluck('academic_year_id')->all());
        });
    }

    public function test_the_class_list_filters_by_year_through_enrolments(): void
    {
        $this->createSchool();
        $data = $this->seedSchoolData();
        $year = $this->inSchool(fn () => AcademicYear::current()->id);
        $empty = $this->inSchool(fn () => AcademicYear::create(['name' => '2099-2100', 'starts_on' => '2099-09-01', 'ends_on' => '2100-06-30', 'status' => 'upcoming'])->id);
        $token = $this->tokenFor('school_admin');

        $this->withToken($token)->getJson('/api/v1/classes')->assertOk()->assertJsonCount(1, 'data');
        $this->withToken($token)->getJson("/api/v1/classes?filter[academic_year_id]={$year}")->assertOk()->assertJsonCount(1, 'data');
        $this->withToken($token)->getJson("/api/v1/classes?filter[academic_year_id]={$empty}")->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_a_class_name_is_unique_per_site_only(): void
    {
        $this->createSchool();
        $data = $this->seedSchoolData();
        $token = $this->tokenFor('school_admin');
        $class = $this->withToken($token)->getJson("/api/v1/classes/{$data['class_id']}")->json('data');

        $payload = ['name' => $class['name'], 'level' => $class['level'] ?? '5e', 'school_id' => $class['school_id']];
        $this->withToken($token)->postJson('/api/v1/classes', $payload)->assertUnprocessable()->assertJsonValidationErrors('name');
    }
}
