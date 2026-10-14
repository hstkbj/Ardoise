<?php

namespace Tests\Feature;

use App\Models\Tenant\FeeAssignment;
use App\Models\Tenant\ReportCard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenantSchool;
use Tests\TestCase;

/** Règle « bulletins et impayés » : off / avertir / bloquer, dérogation par élève, visibilité côté famille. */
class UnpaidRuleTest extends TestCase
{
    use InteractsWithTenantSchool, RefreshDatabase;

    protected function tearDown(): void
    {
        $this->tearDownSchool();
        parent::tearDown();
    }

    /** Awa (élève du jeu de données) a une échéance impayée depuis 60 jours ; ses camarades sont à jour. */
    protected function lateClass(string $rule): array
    {
        $this->createSchool();
        $data = $this->seedSchoolData(150000);
        $fixture = $this->seedGradedClass($data);
        $this->inSchool(fn () => FeeAssignment::where('student_id', $data['student_id'])->update(['due_date' => now()->subDays(60)->toDateString()]));
        $admin = $this->tokenFor('school_admin');

        $this->withToken($admin)->putJson('/api/v1/settings/finance', ['report_card_unpaid_rule' => $rule, 'report_card_unpaid_days' => 30])->assertOk();
        $this->as($admin)->postJson('/api/v1/report-cards/generate', ['class_id' => $data['class_id'], 'term_id' => $fixture['term_id']])->assertOk();

        return [$data, $fixture, $admin];
    }

    protected function publishAll(string $admin, array $fixture)
    {
        return $this->as($admin)->postJson('/api/v1/report-cards/publish', ['ids' => array_values($fixture['students']), 'term_id' => $fixture['term_id']]);
    }

    public function test_with_the_rule_off_everything_is_published(): void
    {
        [, $fixture, $admin] = $this->lateClass('off');

        $this->publishAll($admin, $fixture)->assertOk()->assertJsonPath('count', 3)->assertJsonCount(0, 'blocked')->assertJsonCount(0, 'warnings');
    }

    public function test_in_warn_mode_the_card_is_published_with_a_warning_for_the_school_and_the_family(): void
    {
        [$data, $fixture, $admin] = $this->lateClass('warn');

        $response = $this->publishAll($admin, $fixture)->assertOk()->assertJsonPath('count', 3)->assertJsonCount(0, 'blocked');
        $this->assertSame([$data['student_id']], collect($response->json('warnings'))->pluck('id')->all());

        $parent = $this->codeToken($data['parent_code']);
        $child = $this->as($parent)->getJson('/api/v1/parent/children')->assertOk()->json('data.0');
        $this->assertSame('warn', $child['finance_notice']['mode']);
        $this->assertFalse($child['finance_notice']['blocked']);
        $this->as($parent)->getJson("/api/v1/report-cards/{$data['student_id']}?term_id={$fixture['term_id']}")->assertOk();
    }

    public function test_in_block_mode_the_late_student_is_not_published_and_the_others_are(): void
    {
        [$data, $fixture, $admin] = $this->lateClass('block');

        $response = $this->publishAll($admin, $fixture)->assertOk()->assertJsonPath('count', 2);

        $this->assertSame([$data['student_id']], collect($response->json('blocked'))->pluck('id')->all());
        $this->assertSame('draft', $this->inSchool(fn () => ReportCard::where('student_id', $data['student_id'])->value('status')));
    }

    public function test_the_threshold_decides_whether_an_installment_is_late_enough(): void
    {
        [$data, $fixture, $admin] = $this->lateClass('block');
        $this->as($admin)->putJson('/api/v1/settings/finance', ['report_card_unpaid_rule' => 'block', 'report_card_unpaid_days' => 90])->assertOk();

        $this->publishAll($admin, $fixture)->assertOk()->assertJsonPath('count', 3);
    }

    public function test_a_waiver_lets_the_student_through_and_can_be_removed(): void
    {
        [$data, $fixture, $admin] = $this->lateClass('block');

        $this->as($admin)->postJson("/api/v1/students/{$data['student_id']}/waive", ['note' => 'Accord du directeur'])->assertOk()->assertJsonPath('data.unpaid_waiver', true);
        $this->publishAll($admin, $fixture)->assertOk()->assertJsonPath('count', 3)->assertJsonCount(0, 'blocked');

        $this->as($admin)->postJson("/api/v1/students/{$data['student_id']}/unwaive")->assertOk()->assertJsonPath('data.unpaid_waiver', false);
    }

    public function test_a_card_already_published_is_hidden_from_the_family_when_the_rule_blocks(): void
    {
        [$data, $fixture, $admin] = $this->lateClass('off');
        $this->publishAll($admin, $fixture)->assertOk();
        $parent = $this->codeToken($data['parent_code']);
        $this->as($parent)->getJson("/api/v1/report-cards/{$data['student_id']}?term_id={$fixture['term_id']}")->assertOk();

        $this->as($admin)->putJson('/api/v1/settings/finance', ['report_card_unpaid_rule' => 'block', 'report_card_unpaid_days' => 30])->assertOk();

        $this->as($parent)->getJson("/api/v1/report-cards/{$data['student_id']}?term_id={$fixture['term_id']}")->assertForbidden();
        $this->as($parent)->get("/api/v1/report-cards/{$data['student_id']}/pdf?term_id={$fixture['term_id']}")->assertForbidden();
        $cards = $this->as($parent)->getJson('/api/v1/parent/children')->json('data.0.report_cards');
        $this->assertContains('withheld', collect($cards)->pluck('status')->all());
    }

    public function test_paying_the_late_installment_lifts_the_block(): void
    {
        [$data, $fixture, $admin] = $this->lateClass('block');
        $feeId = $this->inSchool(fn () => FeeAssignment::findOrFail($data['assignment_id'])->fee_id);

        $this->as($admin)->postJson('/api/v1/payments', ['student_id' => $data['student_id'], 'fee_id' => $feeId, 'paid_amount' => 150000, 'method' => 'Espèces'])->assertCreated();

        $this->publishAll($admin, $fixture)->assertOk()->assertJsonPath('count', 3);
    }
}
