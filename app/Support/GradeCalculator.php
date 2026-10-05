<?php

namespace App\Support;

/**
 * Calculs de moyennes et de rangs (sans dépendance au framework).
 *
 * Règles :
 *  - chaque note est ramenée sur 20 puis pondérée par le coefficient de l'évaluation ;
 *  - un élève absent ou sans note n'est pas compté (ce n'est pas un 0) ;
 *  - moyenne générale = moyenne des matières pondérée par le coefficient de la matière ;
 *  - rang « olympique » : les ex æquo partagent le rang (1, 2, 2, 4).
 */
final class GradeCalculator
{
    public function __construct(private float $step = 0.01) {}

    /**
     * @param  array<int, array{score: float|null, max: float, coefficient: float, absent?: bool}>  $marks
     */
    public function subjectAverage(array $marks): ?float
    {
        $sum = 0.0;
        $weights = 0.0;

        foreach ($marks as $mark) {
            if (! empty($mark['absent']) || $mark['score'] === null || $mark['max'] <= 0 || $mark['coefficient'] <= 0) {
                continue;
            }

            $sum += ($mark['score'] / $mark['max']) * 20 * $mark['coefficient'];
            $weights += $mark['coefficient'];
        }

        return $weights > 0 ? $this->round($sum / $weights) : null;
    }

    /**
     * @param  array<int, array{average: float|null, coefficient: float}>  $subjects
     */
    public function generalAverage(array $subjects): ?float
    {
        $sum = 0.0;
        $weights = 0.0;

        foreach ($subjects as $subject) {
            if ($subject['average'] === null || $subject['coefficient'] <= 0) {
                continue;
            }

            $sum += $subject['average'] * $subject['coefficient'];
            $weights += $subject['coefficient'];
        }

        return $weights > 0 ? $this->round($sum / $weights) : null;
    }

    /** @param  array<int|string, float|null>  $values */
    public function average(array $values): ?float
    {
        $values = array_filter($values, fn ($v) => $v !== null);

        return $values ? $this->round(array_sum($values) / count($values)) : null;
    }

    /**
     * @param  array<int|string, float|null>  $averages  identifiant => moyenne
     * @return array<int|string, int|null>  identifiant => rang (null si pas de moyenne)
     */
    public function rank(array $averages): array
    {
        $ranked = array_filter($averages, fn ($v) => $v !== null);
        arsort($ranked);

        $ranks = array_fill_keys(array_keys($averages), null);
        $position = 0;
        $previous = null;
        $currentRank = 0;

        foreach ($ranked as $id => $value) {
            $position++;

            if ($previous === null || abs($value - $previous) > 1e-9) {
                $currentRank = $position;
                $previous = $value;
            }

            $ranks[$id] = $currentRank;
        }

        return $ranks;
    }

    public function round(?float $value): ?float
    {
        if ($value === null) {
            return null;
        }

        $step = $this->step > 0 ? $this->step : 0.01;

        return round(round($value / $step) * $step, 2);
    }

    /** Valide une note saisie (« 14,5 » accepté). Retourne null si vide, false si invalide. */
    public static function parseScore(mixed $input, float $max): float|false|null
    {
        if ($input === null || (is_string($input) && trim($input) === '')) {
            return null;
        }

        $normalized = str_replace(',', '.', trim((string) $input));

        if (! is_numeric($normalized)) {
            return false;
        }

        $value = (float) $normalized;

        return $value < 0 || $value > $max ? false : $value;
    }
}
