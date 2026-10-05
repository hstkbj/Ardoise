<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ligne de l'écran « Paiements » : une échéance (FeeAssignment) avec son
 * dernier paiement. Statuts : paid | partial | pending | overdue.
 */
class PaymentLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $last = $this->lastPayment;
        $fee = $this->fee;
        $class = $this->student?->currentEnrollment?->classRoom;

        return [
            'id' => $this->id,
            'reference' => $last?->reference ?? '—',
            'student_id' => $this->student_id,
            'student_name' => $this->student?->full_name,
            'class_name' => $class?->name,
            'fee_id' => $this->fee_id,
            'fee_name' => $fee ? $fee->name.($fee->installments > 1 ? ', échéance '.$this->installment_no : '') : null,
            'amount' => $this->amount,
            'paid_amount' => $this->paid_amount,
            'remaining' => $this->remaining(),
            'due_date' => $this->due_date?->toDateString(),
            'method' => $last?->method ?? '—',
            'paid_at' => $last?->paid_at?->toDateString(),
            'status' => $this->displayStatus(),
            'payments' => $this->whenLoaded('payments', fn () => $this->payments->map(fn ($p) => [
                'id' => $p->id, 'reference' => $p->reference, 'amount' => $p->amount, 'method' => $p->method,
                'transaction_ref' => $p->transaction_ref, 'paid_at' => $p->paid_at?->toDateString(), 'status' => $p->status,
                'received_by' => $p->receiver?->name,
            ])),
        ];
    }
}
