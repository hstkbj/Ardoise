<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Élève de l'écran « Paiements » : ce qu'il doit pour l'année, ce qu'il a payé et ce qui reste.
 * Les totaux viennent de sous-requêtes (expected, paid, overdue_count) ajoutées par PaymentController::students.
 */
class StudentBalanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $expected = (int) ($this->expected ?? 0);
        $paid = (int) ($this->paid ?? 0);
        $remaining = max(0, $expected - $paid);
        $class = $this->currentEnrollment?->classRoom;

        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'matricule' => $this->matricule,
            'class_id' => $class?->id,
            'class_name' => $class?->name,
            'expected' => $expected,
            'paid' => $paid,
            'remaining' => $remaining,
            'overdue_count' => (int) ($this->overdue_count ?? 0),
            'status' => self::statusFor($expected, $paid, (int) ($this->overdue_count ?? 0)),
        ];
    }

    /** no_fees | paid | overdue | partial | pending */
    public static function statusFor(int $expected, int $paid, int $overdueCount): string
    {
        return match (true) {
            $expected === 0 => 'no_fees',
            $paid >= $expected => 'paid',
            $overdueCount > 0 => 'overdue',
            $paid > 0 => 'partial',
            default => 'pending',
        };
    }
}
