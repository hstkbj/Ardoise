<?php

namespace App\Services;

use App\Models\Tenant\ClassRoom;
use App\Models\Tenant\Enrollment;
use App\Models\Tenant\Fee;
use App\Models\Tenant\FeeAssignment;
use App\Models\Tenant\Student;
use Illuminate\Support\Carbon;

/** Génère les échéances des élèves à partir des frais définis. */
class FeeService
{
    /** Crée les échéances manquantes pour tous les élèves concernés. Retourne le nombre d'élèves traités. */
    public function assign(Fee $fee): int
    {
        if ($fee->status !== 'active') {
            return 0;
        }

        $levelIds = $fee->levels()->pluck('levels.id');
        $count = 0;

        Enrollment::query()
            ->where('status', 'active')
            ->where('academic_year_id', $fee->academic_year_id)
            ->whereHas('classRoom', function ($q) use ($fee, $levelIds) {
                $levelIds->isNotEmpty() && $q->whereIn('level_id', $levelIds);
                $fee->campus_id && $q->where('campus_id', $fee->campus_id);
            })
            ->with('student')
            ->chunkById(200, function ($enrollments) use ($fee, &$count) {
                foreach ($enrollments as $enrollment) {
                    if ($enrollment->student && $this->createFor($fee, $enrollment->student)) {
                        $count++;
                    }
                }
            });

        return $count;
    }

    /** Après une inscription : affecte à l'élève les frais de son niveau / site. */
    public function assignToStudent(Student $student, ClassRoom $class): void
    {
        Fee::query()
            ->where('status', 'active')
            ->where('academic_year_id', $class->academic_year_id)
            ->where(fn ($q) => $q->whereNull('campus_id')->orWhere('campus_id', $class->campus_id))
            ->where(fn ($q) => $q->whereDoesntHave('levels')->orWhereHas('levels', fn ($l) => $l->where('levels.id', $class->level_id)))
            ->each(fn (Fee $fee) => $this->createFor($fee, $student));
    }

    /**
     * Après modification d'un frais : si rien n'a encore été payé, les échéances
     * sont régénérées ; sinon seules les échéances manquantes sont ajoutées.
     */
    public function resync(Fee $fee): void
    {
        if (! $fee->assignments()->where('paid_amount', '>', 0)->exists()) {
            $fee->assignments()->delete();
        }

        $this->assign($fee);
    }

    protected function createFor(Fee $fee, Student $student): bool
    {
        if (FeeAssignment::where('fee_id', $fee->id)->where('student_id', $student->id)->exists()) {
            return false;
        }

        $installments = max(1, (int) $fee->installments);
        $interval = $fee->interval_months ?: max(1, intdiv(9, $installments));
        $first = $fee->first_due_date ? Carbon::parse($fee->first_due_date) : null;

        foreach (self::split($fee->amount, $installments) as $i => $amount) {
            FeeAssignment::create([
                'fee_id' => $fee->id,
                'student_id' => $student->id,
                'installment_no' => $i + 1,
                'amount' => $amount,
                'paid_amount' => 0,
                'due_date' => $first?->copy()->addMonthsNoOverflow($i * $interval)->toDateString(),
                'status' => 'pending',
            ]);
        }

        return true;
    }

    /** 450 000 en 3 → [150 000, 150 000, 150 000] ; le reste va à la dernière échéance. */
    public static function split(int $amount, int $parts): array
    {
        $parts = max(1, $parts);
        $base = intdiv($amount, $parts);
        $result = array_fill(0, $parts, $base);
        $result[$parts - 1] += $amount - $base * $parts;

        return $result;
    }
}
