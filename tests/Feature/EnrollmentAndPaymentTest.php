<?php

namespace Tests\Feature;

use App\Models\Tenant\ClassRoom;
use App\Models\Tenant\Enrollment;
use App\Models\Tenant\Fee;
use App\Models\Tenant\FeeAssignment;
use App\Models\Tenant\Level;
use App\Models\Tenant\ParentProfile;
use App\Models\Tenant\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenantSchool;
use Tests\TestCase;

/** Inscription d'un élève et encaissement manuel. */
class EnrollmentAndPaymentTest extends TestCase
{
    use InteractsWithTenantSchool, RefreshDatabase;

    protected function tearDown(): void
    {
        $this->tearDownSchool();
        parent::tearDown();
    }

    public function test_enrolling_a_student_creates_the_matricule_the_enrollment_and_the_parent_code(): void
    {
        $this->createSchool();
        $data = $this->seedSchoolData();

        $response = $this->withToken($this->tokenFor('school_admin'))->postJson('/api/v1/students', [
            'first_name' => 'Fanta', 'last_name' => 'Diallo', 'gender' => 'F', 'class_id' => $data['class_id'],
            'guardian_name' => 'Aïcha Diallo', 'guardian_phone' => '+22997555000', 'guardian_relation' => 'Mère',
        ])->assertCreated();

        $response->assertJsonPath('data.class_id', $data['class_id']);
        $this->assertNotEmpty($response->json('meta.parent_access_code'));
        $this->assertMatchesRegularExpression('/^TES-\d{2}-\d{4}$/', $response->json('data.matricule'));

        $this->inSchool(function () use ($response, $data) {
            $student = Student::findOrFail($response->json('data.id'));
            $this->assertSame($data['class_id'], $student->currentClass()?->id);
            $this->assertSame('Aïcha', ParentProfile::whereHas('students', fn ($q) => $q->whereKey($student->id))->value('first_name'));
        });
    }

    public function test_a_known_guardian_phone_reuses_the_existing_parent(): void
    {
        $this->createSchool();
        $data = $this->seedSchoolData();

        $this->withToken($this->tokenFor('school_admin'))->postJson('/api/v1/students', [
            'first_name' => 'Ali', 'last_name' => 'Traoré', 'class_id' => $data['class_id'],
            'guardian_name' => 'Mariam Traoré', 'guardian_phone' => '+229 97 00 00 01',
        ])->assertCreated()->assertJsonPath('meta.parent_access_code', null);

        $this->assertSame(1, $this->inSchool(fn () => ParentProfile::count()));
    }

    public function test_changing_class_keeps_the_history_with_a_single_active_enrollment(): void
    {
        $this->createSchool();
        $data = $this->seedSchoolData();
        $other = $this->inSchool(function () use ($data) {
            $class = ClassRoom::findOrFail($data['class_id']);

            return ClassRoom::create([
                'name' => '5e C', 'level_id' => Level::where('name', '5e')->value('id'), 'campus_id' => $class->campus_id,
                'academic_year_id' => $class->academic_year_id, 'status' => 'active',
            ])->id;
        });

        $this->withToken($this->tokenFor('school_admin'))
            ->postJson("/api/v1/students/{$data['student_id']}/change-class", ['class_id' => $other])
            ->assertOk()->assertJsonPath('data.class_id', $other);

        $this->inSchool(function () use ($data) {
            $this->assertSame(2, Enrollment::where('student_id', $data['student_id'])->count());
            $this->assertSame(1, Enrollment::where('student_id', $data['student_id'])->where('status', 'active')->count());
        });
    }

    public function test_a_manual_payment_updates_the_installment_and_can_be_printed(): void
    {
        $this->createSchool();
        $data = $this->seedSchoolData(150000);
        $admin = $this->tokenFor('school_admin');
        $feeId = $this->inSchool(fn () => Fee::value('id'));

        $this->withToken($admin)->postJson('/api/v1/payments', [
            'student_id' => $data['student_id'], 'fee_id' => $feeId, 'paid_amount' => 50000, 'method' => 'Espèces',
        ])->assertCreated();

        $assignment = $this->inSchool(fn () => FeeAssignment::findOrFail($data['assignment_id']));
        $this->assertSame(50000, (int) $assignment->paid_amount);
        $this->assertSame(100000, $assignment->remaining());

        $this->withToken($admin)->postJson('/api/v1/payments', [
            'student_id' => $data['student_id'], 'fee_id' => $feeId, 'paid_amount' => 100000, 'method' => 'Mobile money',
        ])->assertCreated();

        $this->assertSame('paid', $this->inSchool(fn () => FeeAssignment::findOrFail($data['assignment_id'])->status));
        $this->withToken($admin)->get("/api/v1/payments/{$data['assignment_id']}/receipt")->assertOk();
    }

    public function test_a_payment_above_the_remaining_amount_is_refused(): void
    {
        $this->createSchool();
        $data = $this->seedSchoolData(150000);

        $this->withToken($this->tokenFor('school_admin'))->postJson('/api/v1/payments', [
            'student_id' => $data['student_id'], 'fee_id' => $this->inSchool(fn () => Fee::value('id')), 'paid_amount' => 200000, 'method' => 'Espèces',
        ])->assertUnprocessable()->assertJsonValidationErrors('paid_amount');

        $this->assertSame(0, (int) $this->inSchool(fn () => FeeAssignment::findOrFail($data['assignment_id'])->paid_amount));
    }

    public function test_a_teacher_cannot_record_payments(): void
    {
        $this->createSchool();
        $data = $this->seedSchoolData();

        $this->withToken($this->tokenFor('teacher'))->postJson('/api/v1/payments', [
            'student_id' => $data['student_id'], 'fee_id' => $this->inSchool(fn () => Fee::value('id')), 'paid_amount' => 1000, 'method' => 'Espèces',
        ])->assertForbidden();
    }
}
