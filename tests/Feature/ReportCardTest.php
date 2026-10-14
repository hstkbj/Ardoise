<?php

namespace Tests\Feature;

use App\Models\Tenant\ReportCard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenantSchool;
use Tests\TestCase;

/** Notes validées → moyennes, rangs et bulletins. */
class ReportCardTest extends TestCase
{
    use InteractsWithTenantSchool, RefreshDatabase;

    protected function tearDown(): void
    {
        $this->tearDownSchool();
        parent::tearDown();
    }

    public function test_averages_and_ranks_follow_the_subject_coefficients(): void
    {
        $this->createSchool();
        $data = $this->seedSchoolData();
        $fixture = $this->seedGradedClass($data);
        $admin = $this->tokenFor('school_admin');

        $this->withToken($admin)->postJson('/api/v1/report-cards/generate', ['class_id' => $data['class_id'], 'term_id' => $fixture['term_id']])
            ->assertOk()->assertJsonPath('count', 3);

        $rows = collect($this->withToken($admin)->getJson("/api/v1/report-cards?class_id={$data['class_id']}&term_id={$fixture['term_id']}")
            ->assertOk()->json('data'))->keyBy('id');

        $this->assertEqualsWithDelta(14.0, $rows[$fixture['students']['awa']]['general_average'], 0.01);
        $this->assertEqualsWithDelta(12.67, $rows[$fixture['students']['koffi']]['general_average'], 0.01);
        $this->assertEqualsWithDelta(8.0, $rows[$fixture['students']['binta']]['general_average'], 0.01);
        $this->assertSame([1, 2, 3], [$rows[$fixture['students']['awa']]['rank'], $rows[$fixture['students']['koffi']]['rank'], $rows[$fixture['students']['binta']]['rank']]);
    }

    public function test_draft_assessments_are_ignored_in_the_average(): void
    {
        $this->createSchool();
        $data = $this->seedSchoolData();
        $fixture = $this->seedGradedClass($data);
        $this->inSchool(fn () => \App\Models\Tenant\Assessment::query()->update(['status' => 'draft']));

        $rows = collect($this->withToken($this->tokenFor('school_admin'))->getJson("/api/v1/report-cards?class_id={$data['class_id']}&term_id={$fixture['term_id']}")
            ->assertOk()->json('data'));

        $this->assertTrue($rows->every(fn ($r) => $r['general_average'] === null));
    }

    public function test_published_report_cards_are_visible_to_the_parent_only_after_publication(): void
    {
        $this->createSchool();
        $data = $this->seedSchoolData();
        $fixture = $this->seedGradedClass($data);
        $admin = $this->tokenFor('school_admin');
        $awa = $fixture['students']['awa'];

        $this->withToken($admin)->postJson('/api/v1/report-cards/generate', ['class_id' => $data['class_id'], 'term_id' => $fixture['term_id']])->assertOk();
        $parent = $this->codeToken($data['parent_code']);

        $this->as($parent)->getJson("/api/v1/report-cards/{$awa}?term_id={$fixture['term_id']}")->assertNotFound();

        $this->as($admin)->postJson('/api/v1/report-cards/publish', ['ids' => [$awa], 'term_id' => $fixture['term_id']])->assertOk()->assertJsonPath('count', 1);
        $this->assertSame('published', $this->inSchool(fn () => ReportCard::where('student_id', $awa)->value('status')));

        $this->as($parent)->getJson("/api/v1/report-cards/{$awa}?term_id={$fixture['term_id']}")->assertOk();
        $this->as($parent)->get("/api/v1/report-cards/{$awa}/pdf?term_id={$fixture['term_id']}")->assertOk();
    }

    public function test_publishing_requires_generated_report_cards(): void
    {
        $this->createSchool();
        $data = $this->seedSchoolData();
        $fixture = $this->seedGradedClass($data);

        $this->withToken($this->tokenFor('school_admin'))
            ->postJson('/api/v1/report-cards/publish', ['ids' => [$fixture['students']['awa']], 'term_id' => $fixture['term_id']])
            ->assertUnprocessable();
    }
}
