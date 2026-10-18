<?php

namespace Tests\Feature;

use App\Models\Tenant\Assessment;
use App\Models\Tenant\ClassSubject;
use App\Models\Tenant\Grade;
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
        $this->inSchool(fn () => Assessment::query()->update(['status' => 'draft']));

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

    public function test_the_report_card_shows_interrogations_devoirs_and_the_rank_in_each_subject(): void
    {
        $this->createSchool();
        $data = $this->seedGradedClass($this->seedSchoolData());
        $students = $data['students'];
        $this->inSchool(function () use ($data, $students) {
            $maths = $data['class_subject_ids'][0];
            $add = function (string $title, string $type, array $scores, int $max = 20) use ($maths, $data, $students) {
                $assessment = Assessment::create([
                    'class_subject_id' => $maths, 'term_id' => $data['term_id'], 'title' => $title, 'type' => $type,
                    'date' => now()->toDateString(), 'coefficient' => 1, 'max_score' => $max, 'status' => 'validated', 'validated_at' => now(),
                ]);
                foreach ($scores as $key => $score) {
                    Grade::create(['assessment_id' => $assessment->id, 'student_id' => $students[$key], 'score' => $score]);
                }
            };
            $add('Interrogation 1', 'interrogation', ['awa' => 12, 'koffi' => 10, 'binta' => 6]);
            $add('Interrogation 2', 'interrogation', ['awa' => 7, 'koffi' => 5, 'binta' => 4], 10);
            $add('Devoir 2', 'devoir', ['awa' => 18, 'koffi' => 11, 'binta' => 9]);
        });
        $admin = $this->tokenFor('school_admin');
        $this->withToken($admin)->postJson('/api/v1/report-cards/generate', ['class_id' => $this->inSchool(fn () => ClassSubject::find($data['class_subject_ids'][0])->class_room_id), 'term_id' => $data['term_id']])->assertOk();

        // Awa en maths : interrogations 12 et 14 → 13 ; devoirs 16 et 18 ; (13 + 16 + 18) / 3 = 15,67
        $maths = collect($this->withToken($admin)->getJson("/api/v1/report-cards/{$students['awa']}?term_id={$data['term_id']}")->assertOk()->json('data.subjects'))->firstWhere('name', 'Maths');
        $this->assertEquals([12, 14], $maths['interros']);
        $this->assertEquals(13, $maths['interro_average']);
        $this->assertEquals([16, 18], $maths['devoirs']);
        $this->assertEqualsWithDelta(15.67, $maths['average'], 0.001);
        $this->assertSame(1, $maths['rank']);

        $koffi = collect($this->withToken($admin)->getJson("/api/v1/report-cards/{$students['koffi']}?term_id={$data['term_id']}")->json('data.subjects'))->firstWhere('name', 'Maths');
        $this->assertEqualsWithDelta(11.0, $koffi['average'], 0.001);   // (10 + 12 + 11) / 3
        $this->assertSame(2, $koffi['rank']);
    }
}
