<?php

namespace Tests\Feature;

use App\Models\Tenant\ClassSubject;
use App\Models\Tenant\Subject;
use App\Models\Tenant\Teacher;
use App\Models\Tenant\TimetableEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenantSchool;
use Tests\TestCase;

/** Enseignant ↔ matière ↔ classe ↔ emploi du temps. */
class ClassSubjectTeacherTest extends TestCase
{
    use InteractsWithTenantSchool, RefreshDatabase;

    protected function tearDown(): void
    {
        $this->tearDownSchool();
        parent::tearDown();
    }

    /** Deux matières, un enseignant de maths (Kofi) et un de français (Awa). */
    protected function setUpSubjects(): array
    {
        return $this->inSchool(function () {
            $maths = Subject::create(['name' => 'Mathématiques', 'code' => 'MATH', 'default_coefficient' => 3, 'status' => 'active']);
            $french = Subject::create(['name' => 'Français', 'code' => 'FR', 'default_coefficient' => 3, 'status' => 'active']);
            $kofi = Teacher::create(['first_name' => 'Kofi', 'last_name' => 'Mensah', 'status' => 'active']);
            $awa = Teacher::create(['first_name' => 'Awa', 'last_name' => 'Sow', 'status' => 'active']);
            $kofi->subjects()->sync([$maths->id]);
            $awa->subjects()->sync([$french->id]);

            return ['maths' => $maths->id, 'french' => $french->id, 'kofi' => $kofi->id, 'awa' => $awa->id];
        });
    }

    public function test_the_class_lists_subjects_with_only_the_teachers_of_each_subject(): void
    {
        $this->createSchool();
        $class = $this->seedSchoolData()['class_id'];
        $ids = $this->setUpSubjects();

        $rows = collect($this->withToken($this->tokenFor('school_admin'))->getJson("/api/v1/classes/{$class}/subjects")->assertOk()->json('data'))->keyBy('subject_id');

        $this->assertSame([$ids['kofi']], array_column($rows[$ids['maths']]['teachers'], 'value'));
        $this->assertSame([$ids['awa']], array_column($rows[$ids['french']]['teachers'], 'value'));
        $this->assertFalse($rows[$ids['maths']]['assigned']);
    }

    public function test_a_teacher_who_does_not_teach_the_subject_cannot_be_assigned(): void
    {
        $this->createSchool();
        $class = $this->seedSchoolData()['class_id'];
        $ids = $this->setUpSubjects();
        $token = $this->tokenFor('school_admin');

        $this->withToken($token)->putJson("/api/v1/classes/{$class}/subjects", ['subjects' => [['subject_id' => $ids['maths'], 'teacher_id' => $ids['awa'], 'coefficient' => 3]]])
            ->assertUnprocessable()->assertJsonValidationErrors('subjects.0.teacher_id');

        $this->withToken($token)->putJson("/api/v1/classes/{$class}/subjects", ['subjects' => [['subject_id' => $ids['maths'], 'teacher_id' => $ids['kofi'], 'coefficient' => 3]]])
            ->assertOk();

        $this->inSchool(fn () => $this->assertSame($ids['kofi'], ClassSubject::where('class_room_id', $class)->where('subject_id', $ids['maths'])->value('teacher_id')));
    }

    public function test_a_timetable_slot_needs_a_subject_assigned_to_the_class(): void
    {
        $this->createSchool();
        $class = $this->seedSchoolData()['class_id'];
        $ids = $this->setUpSubjects();

        $this->withToken($this->tokenFor('school_admin'))->postJson('/api/v1/timetables/entries', ['class_id' => $class, 'subject_id' => $ids['maths'], 'day' => 0, 'start' => '08:00', 'end' => '09:00'])
            ->assertUnprocessable()->assertJsonValidationErrors('subject_id');
    }

    public function test_the_teacher_of_the_subject_is_used_by_default_in_the_timetable(): void
    {
        $this->createSchool();
        $class = $this->seedSchoolData()['class_id'];
        $ids = $this->setUpSubjects();
        $token = $this->tokenFor('school_admin');

        $this->withToken($token)->putJson("/api/v1/classes/{$class}/subjects", ['subjects' => [['subject_id' => $ids['maths'], 'teacher_id' => $ids['kofi'], 'coefficient' => 3]]])->assertOk();

        $this->withToken($token)->postJson('/api/v1/timetables/entries', ['class_id' => $class, 'subject_id' => $ids['maths'], 'day' => 0, 'start' => '08:00', 'end' => '09:00'])
            ->assertCreated()->assertJsonPath('data.teacher_id', $ids['kofi']);

        $this->inSchool(fn () => $this->assertSame(1, TimetableEntry::where('teacher_id', $ids['kofi'])->count()));
    }

    public function test_the_timetable_refuses_a_teacher_who_does_not_teach_the_subject(): void
    {
        $this->createSchool();
        $class = $this->seedSchoolData()['class_id'];
        $ids = $this->setUpSubjects();
        $token = $this->tokenFor('school_admin');

        $this->withToken($token)->putJson("/api/v1/classes/{$class}/subjects", ['subjects' => [['subject_id' => $ids['maths'], 'teacher_id' => $ids['kofi'], 'coefficient' => 3]]])->assertOk();

        $this->withToken($token)->postJson('/api/v1/timetables/entries', ['class_id' => $class, 'subject_id' => $ids['maths'], 'teacher_id' => $ids['awa'], 'day' => 1, 'start' => '08:00', 'end' => '09:00'])
            ->assertUnprocessable()->assertJsonValidationErrors('teacher_id');
    }
}
