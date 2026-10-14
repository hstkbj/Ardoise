<?php

namespace App\Services;

use App\Models\Tenant\FeeAssignment;
use App\Models\Tenant\Setting;
use App\Models\Tenant\Student;
use Illuminate\Support\Collection;

/**
 * Règle « bulletins et impayés » (paramètres finance) :
 *   off   : aucune règle
 *   warn  : le bulletin reste publié, l'école et la famille sont avertis
 *   block : le bulletin n'est ni publié ni visible tant que les échéances en retard ne sont pas réglées
 * Le retard se mesure en jours après l'échéance (seuil « report_card_unpaid_days »).
 * Une dérogation par élève (students.unpaid_waiver) suspend la règle.
 */
class UnpaidRule
{
    public const OFF = 'off';

    public const WARN = 'warn';

    public const BLOCK = 'block';

    public function mode(): string
    {
        $mode = (string) Setting::get('finance', 'report_card_unpaid_rule', self::OFF);

        return tenant()?->hasFeature('finance') && in_array($mode, [self::WARN, self::BLOCK], true) ? $mode : self::OFF;
    }

    public function thresholdDays(): int
    {
        return max(0, (int) Setting::get('finance', 'report_card_unpaid_days', 30));
    }

    /** @return Collection<int, FeeAssignment> */
    public function lateAssignments(Student $student): Collection
    {
        $limit = now()->subDays($this->thresholdDays())->toDateString();

        return FeeAssignment::where('student_id', $student->id)
            ->where('status', '!=', 'paid')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $limit)
            ->get()
            ->filter(fn (FeeAssignment $a) => $a->remaining() > 0)
            ->values();
    }

    /** @return array{mode: string, applies: bool, blocked: bool, warned: bool, waived: bool, late_count: int, late_amount: int} */
    public function status(Student $student): array
    {
        $mode = $this->mode();
        $late = $mode === self::OFF ? collect() : $this->lateAssignments($student);
        $applies = $mode !== self::OFF && $late->isNotEmpty() && ! $student->unpaid_waiver;

        return [
            'mode' => $mode,
            'applies' => $applies,
            'blocked' => $applies && $mode === self::BLOCK,
            'warned' => $applies && $mode === self::WARN,
            'waived' => $mode !== self::OFF && $late->isNotEmpty() && $student->unpaid_waiver,
            'late_count' => $late->count(),
            'late_amount' => (int) $late->sum(fn (FeeAssignment $a) => $a->remaining()),
        ];
    }

    public function blocks(Student $student): bool
    {
        return $this->status($student)['blocked'];
    }
}
