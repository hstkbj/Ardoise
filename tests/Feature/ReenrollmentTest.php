<?php

namespace Tests\Feature;

use App\Models\Tenant\AcademicYear;
use App\Models\Tenant\ClassRoom;
use App\Models\Tenant\Enrollment;
use App\Models\Tenant\Fee;
use App\Models\Tenant\FeeAssignment;
use App\Models\Tenant\Level;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenantSchool;
use Tests\TestCase;

/** Réinscription : les élèves déjà présents passent dans leur classe de l'année suivante. */
class ReenrollmentTest extends TestCase
{
    use InteractsWithTenantSchool, RefreshDatabase;

    protected function tearDown(): void
    {
        $this->tearDownSchool();
        parent::tearDown();
    }

    /** Année 1 clôturée, année 2 active avec ses frais et une classe de 4e sur le même site. */
    protected function startNextYear(array $data): array
    {
        return $this->inSchool(function () use ($data) {
            $old = AcademicYear::current();
            $old->update(['status' => 'closed']);
            $old->enrollments()->where('status', 'active')->update(['status' => 'completed']);
            $next = AcademicYear::create(['name' => '2099-2100', 'starts_on' => '2099-09-01', 'ends_on' => '2100-06-30', 'period_type' => 'trimester', 'status' => 'active']);

            $from = ClassRoom::findOrFail($data['class_id']);
            $fourth = ClassRoom::create(['name' => '4e A', 'level_id' => Level::where('name', '4e')->value('id'), 'campus_id' => $from->campus_id, 'status' => 'active']);
            $fee = Fee::create(['academic_year_id' => $next->id, 'name' => 'Scolarité 2099', 'category' => 'scolarite', 'amount' => 200000, 'installments' => 1, 'first_due_date' => '2099-10-01', 'status' => 'active']);

            return ['year_id' => $next->id, 'fourth_id' => $fourth->id, 'fee_id' => $fee->id];
        });
    }

    public function test_students_of_the_previous_year_are_listed_with_the_next_level_suggested(): void
    {
        $this->createSchool();
        $data = $this->seedSchoolData();
        $next = $this->startNextYear($data);

        $out = $this->withToken($this->tokenFor('school_admin'))->getJson('/api/v1/reenrollment')->assertOk()->json('data');

        $this->assertTrue($out['fees_defined']);
        $this->assertCount(1, $out['students']);
        $this->assertSame($data['student_id'], $out['students'][0]['student_id']);
        $this->assertSame($data['class_id'], $out['students'][0]['from_class_id']);
        $this->assertSame($next['fourth_id'], $out['students'][0]['suggested_class_id']);
    }

    public function test_reenrolling_moves_the_student_and_assigns_the_new_year_fees(): void
    {
        $this->createSchool();
        $data = $this->seedSchoolData();
        $next = $this->startNextYear($data);
        $token = $this->tokenFor('school_admin');

        $this->withToken($token)->postJson('/api/v1/reenrollment', ['items' => [['student_id' => $data['student_id'], 'class_id' => $next['fourth_id']]]])
            ->assertOk()->assertJsonPath('data.enrolled', 1);

        $this->inSchool(function () use ($data, $next) {
            $current = Enrollment::where('student_id', $data['student_id'])->where('status', 'active')->first();
            $this->assertSame($next['year_id'], $current->academic_year_id);
            $this->assertSame($next['fourth_id'], $current->class_room_id);
            $this->assertSame(2, Enrollment::where('student_id', $data['student_id'])->count());
            $this->assertTrue(FeeAssignment::where('student_id', $data['student_id'])->where('fee_id', $next['fee_id'])->exists());
        });

        $this->withToken($token)->getJson('/api/v1/reenrollment')->assertOk()->assertJsonCount(0, 'data.students');
    }

    public function test_a_student_can_repeat_the_same_class(): void
    {
        $this->createSchool();
        $data = $this->seedSchoolData();
        $this->startNextYear($data);

        $this->withToken($this->tokenFor('school_admin'))->postJson('/api/v1/reenrollment', ['items' => [['student_id' => $data['student_id'], 'class_id' => $data['class_id']]]])
            ->assertOk()->assertJsonPath('data.enrolled', 1);

        $this->inSchool(fn () => $this->assertSame($data['class_id'], Enrollment::where('student_id', $data['student_id'])->where('status', 'active')->value('class_room_id')));
    }

    public function test_an_already_enrolled_student_is_skipped(): void
    {
        $this->createSchool();
        $data = $this->seedSchoolData();
        $next = $this->startNextYear($data);
        $token = $this->tokenFor('school_admin');
        $payload = ['items' => [['student_id' => $data['student_id'], 'class_id' => $next['fourth_id']]]];

        $this->withToken($token)->postJson('/api/v1/reenrollment', $payload)->assertOk();
        $this->withToken($token)->postJson('/api/v1/reenrollment', $payload)->assertOk()->assertJsonPath('data.enrolled', 0)->assertJsonPath('data.skipped', 1);

        $this->inSchool(fn () => $this->assertSame(2, Enrollment::where('student_id', $data['student_id'])->count()));
    }

    public function test_reenrollment_is_refused_without_an_open_year(): void
    {
        $this->createSchool();
        $data = $this->seedSchoolData();
        $this->inSchool(fn () => AcademicYear::current()->update(['status' => 'closed']));

        $this->withToken($this->tokenFor('school_admin'))->getJson('/api/v1/reenrollment')->assertUnprocessable();
    }

    public function test_the_teacher_cannot_reenroll(): void
    {
        $this->createSchool();
        $data = $this->seedSchoolData();

        $this->withToken($this->tokenFor('teacher'))->getJson('/api/v1/reenrollment')->assertForbidden();
    }
}
