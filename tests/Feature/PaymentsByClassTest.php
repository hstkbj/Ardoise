<?php

namespace Tests\Feature;

use App\Models\Tenant\ClassRoom;
use App\Models\Tenant\Level;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenantSchool;
use Tests\TestCase;

/** Paiements : on filtre par classe, puis on ouvre un élève pour voir ses frais et ce qui lui reste à payer. */
class PaymentsByClassTest extends TestCase
{
    use InteractsWithTenantSchool, RefreshDatabase;

    protected function tearDown(): void
    {
        $this->tearDownSchool();
        parent::tearDown();
    }

    protected function pay(string $token, array $data, int $amount): void
    {
        $assignmentFee = $this->inSchool(fn () => \App\Models\Tenant\FeeAssignment::findOrFail($data['assignment_id'])->fee_id);

        $this->withToken($token)->postJson('/api/v1/payments', ['student_id' => $data['student_id'], 'fee_id' => $assignmentFee, 'paid_amount' => $amount, 'method' => 'Espèces', 'paid_at' => now()->toDateString()])->assertCreated();
    }

    public function test_the_list_shows_the_students_of_the_chosen_class_with_their_balance(): void
    {
        $this->createSchool();
        $data = $this->seedSchoolData(150000);
        $token = $this->tokenFor('school_admin');

        $row = $this->withToken($token)->getJson("/api/v1/payments/students?filter[class_id]={$data['class_id']}")->assertOk()->assertJsonCount(1, 'data')->json('data.0');

        $this->assertSame($data['student_id'], $row['id']);
        $this->assertSame(150000, $row['expected']);
        $this->assertSame(0, $row['paid']);
        $this->assertSame(150000, $row['remaining']);
        $this->assertSame('pending', $row['status']);

        $otherClass = $this->inSchool(fn () => ClassRoom::create(['name' => '6e A', 'level_id' => Level::where('name', '6e')->value('id'), 'campus_id' => ClassRoom::findOrFail($data['class_id'])->campus_id, 'status' => 'active'])->id);
        $this->withToken($token)->getJson("/api/v1/payments/students?filter[class_id]={$otherClass}")->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_the_balance_follows_the_payments_and_the_status_filter(): void
    {
        $this->createSchool();
        $data = $this->seedSchoolData(150000);
        $token = $this->tokenFor('school_admin');
        $url = "/api/v1/payments/students?filter[class_id]={$data['class_id']}";

        $this->pay($token, $data, 50000);

        $row = $this->withToken($token)->getJson($url)->json('data.0');
        $this->assertSame([50000, 100000, 'partial'], [$row['paid'], $row['remaining'], $row['status']]);
        $this->withToken($token)->getJson($url.'&filter[status]=remaining')->assertJsonCount(1, 'data');
        $this->withToken($token)->getJson($url.'&filter[status]=settled')->assertJsonCount(0, 'data');

        $this->pay($token, $data, 100000);

        $this->withToken($token)->getJson($url)->assertJsonPath('data.0.status', 'paid')->assertJsonPath('data.0.remaining', 0);
        $this->withToken($token)->getJson($url.'&filter[status]=settled')->assertJsonCount(1, 'data');
        $this->withToken($token)->getJson($url.'&filter[status]=remaining')->assertJsonCount(0, 'data');
    }

    public function test_a_student_without_fees_is_listed_as_such(): void
    {
        $this->createSchool();
        $data = $this->seedSchoolData(150000);
        $this->inSchool(fn () => \App\Models\Tenant\FeeAssignment::query()->delete());

        $this->withToken($this->tokenFor('school_admin'))->getJson("/api/v1/payments/students?filter[class_id]={$data['class_id']}")->assertJsonPath('data.0.status', 'no_fees');
        $this->withToken($this->tokenFor('school_admin'))->getJson("/api/v1/payments/students?filter[class_id]={$data['class_id']}&filter[status]=none")->assertJsonCount(1, 'data');
    }

    public function test_the_student_page_gives_fees_totals_and_payments(): void
    {
        $this->createSchool();
        $data = $this->seedSchoolData(150000);
        $token = $this->tokenFor('school_admin');
        $this->pay($token, $data, 60000);

        $out = $this->withToken($token)->getJson("/api/v1/payments/students/{$data['student_id']}")->assertOk()->json('data');

        $this->assertSame([150000, 60000, 90000], [$out['totals']['expected'], $out['totals']['paid'], $out['totals']['remaining']]);
        $this->assertSame('partial', $out['totals']['status']);
        $this->assertCount(1, $out['fees']);
        $this->assertSame(90000, $out['fees'][0]['remaining']);
        $this->assertCount(1, $out['fees'][0]['payments']);
        $this->assertSame('5e B', $out['student']['class_name']);
    }

    public function test_a_teacher_cannot_see_the_payments(): void
    {
        $this->createSchool();
        $data = $this->seedSchoolData();

        $this->withToken($this->tokenFor('teacher', 'prof@ecole.test'))->getJson("/api/v1/payments/students?filter[class_id]={$data['class_id']}")->assertForbidden();
    }
}
