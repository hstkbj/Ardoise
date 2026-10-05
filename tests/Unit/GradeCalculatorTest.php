<?php

namespace Tests\Unit;

use App\Support\GradeCalculator;
use PHPUnit\Framework\TestCase;

class GradeCalculatorTest extends TestCase
{
    public function test_subject_average_is_weighted_and_normalized_to_20(): void
    {
        $avg = (new GradeCalculator)->subjectAverage([
            ['score' => 15, 'max' => 20, 'coefficient' => 3],
            ['score' => 8, 'max' => 10, 'coefficient' => 1],
        ]);

        $this->assertSame(15.25, $avg);
    }

    public function test_absent_students_are_excluded(): void
    {
        $calc = new GradeCalculator;

        $this->assertSame(12.0, $calc->subjectAverage([
            ['score' => null, 'max' => 20, 'coefficient' => 1, 'absent' => true],
            ['score' => 12, 'max' => 20, 'coefficient' => 1],
        ]));
        $this->assertNull($calc->subjectAverage([['score' => null, 'max' => 20, 'coefficient' => 1, 'absent' => true]]));
    }

    public function test_general_average_uses_subject_coefficients_and_skips_empty_subjects(): void
    {
        $avg = (new GradeCalculator)->generalAverage([
            ['average' => 15, 'coefficient' => 3],
            ['average' => 12.5, 'coefficient' => 3],
            ['average' => null, 'coefficient' => 2],
            ['average' => 14, 'coefficient' => 2],
        ]);

        $this->assertSame(13.81, $avg);
    }

    public function test_ties_share_the_same_rank(): void
    {
        $ranks = (new GradeCalculator)->rank(['a' => 12, 'b' => 15, 'c' => 15, 'd' => 9, 'e' => null]);

        $this->assertSame(['a' => 3, 'b' => 1, 'c' => 1, 'd' => 4, 'e' => null], $ranks);
    }

    public function test_rounding_step_is_configurable(): void
    {
        $this->assertSame(14.0, (new GradeCalculator(0.25))->round(13.88));
        $this->assertSame(13.9, (new GradeCalculator(0.1))->round(13.86));
    }

    public function test_score_parsing_accepts_comma_and_rejects_out_of_range(): void
    {
        $this->assertSame(14.5, GradeCalculator::parseScore('14,5', 20));
        $this->assertFalse(GradeCalculator::parseScore('22', 20));
        $this->assertFalse(GradeCalculator::parseScore('abc', 20));
        $this->assertNull(GradeCalculator::parseScore('', 20));
    }
}
