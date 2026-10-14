<?php

namespace Tests\Feature;

use App\Models\Tenant\ClassRoom;
use App\Models\Tenant\Level;
use App\Models\Tenant\Student;
use App\Services\AccountService;
use App\Services\EnrollmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenantSchool;
use Tests\TestCase;

/** Portée des enseignants et étanchéité entre familles. */
class AccessScopeTest extends TestCase
{
    use InteractsWithTenantSchool, RefreshDatabase;

    protected function tearDown(): void
    {
        $this->tearDownSchool();
        parent::tearDown();
    }

    /** @return array{student_id: int, parent_code: string} élève et parent d'une autre classe */
    protected function otherFamily(array $data): array
    {
        return $this->inSchool(function () use ($data) {
            $class = ClassRoom::findOrFail($data['class_id']);
            $other = ClassRoom::create([
                'name' => '3e A', 'level_id' => Level::where('name', '5e')->value('id'), 'campus_id' => $class->campus_id,
                'academic_year_id' => $class->academic_year_id, 'status' => 'active',
            ]);
            $student = Student::create(['matricule' => 'T-0099', 'first_name' => 'Yao', 'last_name' => 'KOUASSI', 'status' => 'active']);
            app(EnrollmentService::class)->enroll($student, $other);
            [$parent, $code] = app(AccountService::class)->createParent(['first_name' => 'Paul', 'last_name' => 'Kouassi', 'phone' => '+22997000002']);
            $parent->students()->attach($student->id, ['relation' => 'Père', 'is_primary' => true]);

            return ['student_id' => $student->id, 'parent_code' => $code];
        });
    }

    public function test_a_teacher_only_sees_the_students_of_their_classes(): void
    {
        $this->createSchool();
        $data = $this->seedSchoolData();
        $other = $this->otherFamily($data);
        $teacher = $this->inSchool(fn () => \App\Models\Tenant\Teacher::firstOrFail()->user->createToken('t')->plainTextToken);
        $teacher = \App\Tenancy\TenantResolver::prefixToken($this->tenant, $teacher);

        $ids = collect($this->withToken($teacher)->getJson('/api/v1/students')->assertOk()->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($data['student_id']));
        $this->assertFalse($ids->contains($other['student_id']));

        $this->as($teacher)->getJson("/api/v1/students/{$data['student_id']}")->assertOk();
        $this->as($teacher)->getJson("/api/v1/students/{$other['student_id']}")->assertForbidden();
    }

    public function test_a_teacher_cannot_import_students(): void
    {
        $this->createSchool();
        $this->seedSchoolData();

        $this->withToken($this->tokenFor('teacher'))->postJson('/api/v1/imports/students')->assertForbidden();
    }

    public function test_a_parent_only_reaches_their_own_children(): void
    {
        $this->createSchool();
        $data = $this->seedSchoolData();
        $other = $this->otherFamily($data);
        $parent = $this->codeToken($data['parent_code']);

        $children = collect($this->as($parent)->getJson('/api/v1/parent/children')->assertOk()->json('data'));
        $this->assertSame([$data['student_id']], $children->pluck('id')->all());

        $this->as($parent)->getJson("/api/v1/parent/children/{$other['student_id']}")->assertForbidden();
        $this->as($parent)->getJson("/api/v1/report-cards/{$other['student_id']}")->assertForbidden();
        $this->as($parent)->getJson("/api/v1/parent/documents?student_id={$other['student_id']}")->assertForbidden();
    }

    public function test_a_parent_cannot_use_staff_routes(): void
    {
        $this->createSchool();
        $data = $this->seedSchoolData();
        $parent = $this->codeToken($data['parent_code']);

        $this->as($parent)->getJson('/api/v1/students')->assertForbidden();
        $this->as($parent)->getJson('/api/v1/payments')->assertForbidden();
    }
}
