<?php

namespace Tests\Feature;

use App\Models\Tenant\ClassSubject;
use App\Models\Tenant\Subject;
use App\Models\Tenant\Teacher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenantSchool;
use Tests\TestCase;

/** Évaluations et devoirs : seules les matières affectées à la classe (et à l'enseignant) sont utilisables. */
class AssessmentSubjectScopeTest extends TestCase
{
    use InteractsWithTenantSchool, RefreshDatabase;

    protected function tearDown(): void
    {
        $this->tearDownSchool();
        parent::tearDown();
    }

    /** Maths et Français à Moussa ; Chimie à un autre enseignant ; Histoire affectée à personne. */
    protected function setUpClass(): array
    {
        $data = $this->seedSchoolData();
        $this->seedGradedClass($data);

        $extra = $this->inSchool(function () use ($data) {
            $other = Teacher::create(['first_name' => 'Aya', 'last_name' => 'Zinsou', 'status' => 'active']);
            $chemistry = Subject::create(['name' => 'Chimie', 'code' => 'CHIM', 'default_coefficient' => 2, 'status' => 'active']);
            $history = Subject::create(['name' => 'Histoire', 'code' => 'HIST', 'default_coefficient' => 2, 'status' => 'active']);
            ClassSubject::create(['class_room_id' => $data['class_id'], 'subject_id' => $chemistry->id, 'teacher_id' => $other->id, 'coefficient' => 2]);

            return ['chemistry' => $chemistry->id, 'history' => $history->id, 'maths' => Subject::where('code', 'MATHS')->value('id')];
        });

        return $data + $extra;
    }

    protected function payload(array $data, int $subjectId): array
    {
        return ['title' => 'Devoir 2', 'type' => 'devoir', 'date' => now()->toDateString(), 'class_id' => $data['class_id'], 'subject_id' => $subjectId, 'coefficient' => 1, 'max_score' => 20];
    }

    public function test_the_teacher_only_gets_his_own_subjects_in_the_options(): void
    {
        $this->createSchool();
        $data = $this->setUpClass();

        $rows = $this->withToken($this->tokenFor('teacher', 'prof@ecole.test'))->getJson('/api/v1/options')->assertOk()->json('data.classSubjects');
        $subjects = collect($rows)->where('class_id', $data['class_id'])->pluck('value')->all();

        $this->assertContains($data['maths'], $subjects);
        $this->assertNotContains($data['chemistry'], $subjects);
        $this->assertNotContains($data['history'], $subjects);
    }

    public function test_the_admin_gets_every_subject_assigned_to_the_class(): void
    {
        $this->createSchool();
        $data = $this->setUpClass();

        $subjects = collect($this->withToken($this->tokenFor('school_admin'))->getJson('/api/v1/options')->json('data.classSubjects'))->where('class_id', $data['class_id'])->pluck('value')->all();

        $this->assertContains($data['maths'], $subjects);
        $this->assertContains($data['chemistry'], $subjects);
        $this->assertNotContains($data['history'], $subjects);
    }

    public function test_the_teacher_creates_an_assessment_in_his_own_subject_only(): void
    {
        $this->createSchool();
        $data = $this->setUpClass();
        $token = $this->tokenFor('teacher', 'prof@ecole.test');

        $this->withToken($token)->postJson('/api/v1/assessments', $this->payload($data, $data['maths']))->assertCreated();
        $this->withToken($token)->postJson('/api/v1/assessments', $this->payload($data, $data['chemistry']))->assertUnprocessable()->assertJsonValidationErrors('subject_id');
        $this->withToken($token)->postJson('/api/v1/assessments', $this->payload($data, $data['history']))->assertUnprocessable()->assertJsonValidationErrors('subject_id');
    }

    public function test_a_subject_not_assigned_to_the_class_is_never_created_on_the_fly(): void
    {
        $this->createSchool();
        $data = $this->setUpClass();

        $this->withToken($this->tokenFor('school_admin'))->postJson('/api/v1/assessments', $this->payload($data, $data['history']))->assertUnprocessable()->assertJsonValidationErrors('subject_id');

        $this->inSchool(fn () => $this->assertFalse(ClassSubject::where('class_room_id', $data['class_id'])->where('subject_id', $data['history'])->exists()));
    }

    public function test_homework_follows_the_same_rule(): void
    {
        $this->createSchool();
        $data = $this->setUpClass();
        $token = $this->tokenFor('teacher', 'prof@ecole.test');
        $payload = fn (int $subject) => ['title' => 'Exercice', 'subject_id' => $subject, 'class_id' => $data['class_id'], 'due_date' => now()->addDays(3)->toDateString()];

        $this->withToken($token)->postJson('/api/v1/homework', $payload($data['maths']))->assertCreated();
        $this->withToken($token)->postJson('/api/v1/homework', $payload($data['chemistry']))->assertUnprocessable()->assertJsonValidationErrors('subject_id');
    }
}
