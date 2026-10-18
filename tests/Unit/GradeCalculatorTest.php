<?php

namespace Tests\Unit;

use App\Support\GradeCalculator;
use PHPUnit\Framework\TestCase;

class GradeCalculatorTest extends TestCase
{
    public function test_subject_average_is_the_interrogation_average_plus_the_two_devoirs_divided_by_three(): void
    {
        $detail = (new GradeCalculator)->subjectBreakdown([
            ['score' => 12, 'max' => 20, 'type' => 'interrogation'],
            ['score' => 7, 'max' => 10, 'type' => 'interrogation'],   // ramenée sur 20 : 14
            ['score' => 10, 'max' => 20, 'type' => 'oral'],
            ['score' => 15, 'max' => 20, 'type' => 'devoir'],
            ['score' => 9, 'max' => 20, 'type' => 'composition'],
        ]);

        // Moyenne d'interrogation (12 + 14 + 10) / 3 = 12 ; moyenne (12 + 15 + 9) / 3 = 12
        $this->assertSame([12.0, 14.0, 10.0], $detail['interros']);
        $this->assertSame(12.0, $detail['interro_average']);
        $this->assertSame([15.0, 9.0], $detail['devoirs']);
        $this->assertSame(12.0, $detail['average']);
    }

    public function test_a_missing_note_is_left_out_of_the_division(): void
    {
        $calc = new GradeCalculator;

        // Un seul devoir : (moyenne d'interrogation + devoir) / 2
        $this->assertSame(13.5, $calc->subjectAverage([
            ['score' => 12, 'max' => 20, 'type' => 'interrogation'],
            ['score' => 15, 'max' => 20, 'type' => 'devoir'],
            ['score' => null, 'max' => 20, 'type' => 'devoir', 'absent' => true],
        ]));
        // Pas encore d'interrogation : moyenne des devoirs
        $this->assertSame(11.0, $calc->subjectAverage([['score' => 13, 'max' => 20, 'type' => 'devoir'], ['score' => 9, 'max' => 20, 'type' => 'examen']]));
        // Seulement des interrogations
        $this->assertSame(11.0, $calc->subjectAverage([['score' => 13, 'max' => 20, 'type' => 'interrogation'], ['score' => 9, 'max' => 20, 'type' => 'controle_continu']]));
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
