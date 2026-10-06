<?php

namespace App\Services;

use App\Models\Tenant\ActivityLog;
use App\Models\Tenant\Fee;
use App\Models\Tenant\FeeAssignment;
use App\Models\Tenant\Payment;
use App\Models\Tenant\Setting;
use App\Models\Tenant\Student;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Encaissements : répartition sur les échéances, reçus, confirmation des paiements en ligne. */
class PaymentService
{
    public function __construct(protected Notifier $notifier) {}

    /**
     * Encaisse un montant pour un frais : il est réparti sur les échéances
     * non soldées, de la plus ancienne à la plus récente.
     *
     * @return Collection<int, Payment>
     */
    public function record(Student $student, Fee $fee, int $amount, string $method, ?string $paidAt = null, ?string $transactionRef = null, ?string $note = null): Collection
    {
        $payments = DB::connection('tenant')->transaction(function () use ($student, $fee, $amount, $method, $paidAt, $transactionRef, $note) {
            $assignments = FeeAssignment::where('student_id', $student->id)
                ->where('fee_id', $fee->id)
                ->where('status', '!=', 'paid')
                ->orderBy('installment_no')
                ->lockForUpdate()
                ->get();

            $due = $assignments->sum(fn (FeeAssignment $a) => $a->remaining());

            if ($due === 0) {
                throw ValidationException::withMessages(['fee_id' => 'Ce frais est déjà entièrement réglé pour cet élève.']);
            }

            if ($amount > $due) {
                throw ValidationException::withMessages(['paid_amount' => 'Le montant dépasse le reste dû ('.number_format($due, 0, ',', ' ').' FCFA).']);
            }

            $left = $amount;
            $created = collect();

            foreach ($assignments as $assignment) {
                if ($left <= 0) {
                    break;
                }

                $part = min($left, $assignment->remaining());
                $created->push($this->createPayment($assignment, $part, $method, $paidAt, $transactionRef, $note, 'paid'));
                $this->apply($assignment, $part);
                $left -= $part;
            }

            return $created;
        });

        ActivityLog::record('payment.recorded', $student, ['amount' => $amount, 'fee' => $fee->name, 'references' => $payments->pluck('reference')]);

        $this->notifier->event(
            'payment.received',
            'Paiement reçu',
            number_format($amount, 0, ',', ' ').' FCFA reçus pour '.$fee->name.'. Merci.',
            $student,
            ['parent' => '/parent/payments', 'staff' => '/admin/payments'],
        );

        return $payments;
    }

    /** Demande de paiement en ligne (statut « pending » tant que non confirmée). */
    public function createPending(FeeAssignment $assignment, int $amount, string $method, ?string $transactionRef): Payment
    {
        return $this->createPayment($assignment, $amount, $method, null, $transactionRef, 'Paiement en ligne à confirmer', 'pending');
    }

    /** Confirme un paiement en attente (retour de l'agrégateur ou validation comptable). */
    public function confirm(Payment $payment): Payment
    {
        if ($payment->status !== 'pending') {
            return $payment;
        }

        DB::connection('tenant')->transaction(function () use ($payment) {
            $assignment = FeeAssignment::lockForUpdate()->findOrFail($payment->fee_assignment_id);
            $amount = min($payment->amount, $assignment->remaining());
            $payment->update(['status' => 'paid', 'amount' => $amount, 'paid_at' => now()->toDateString(), 'received_by' => auth()->id()]);
            $this->apply($assignment, $amount);
        });

        ActivityLog::record('payment.confirmed', $payment);

        return $payment->refresh();
    }

    public function cancel(Payment $payment): void
    {
        DB::connection('tenant')->transaction(function () use ($payment) {
            if ($payment->status === 'paid') {
                $assignment = FeeAssignment::lockForUpdate()->findOrFail($payment->fee_assignment_id);
                $this->apply($assignment, -$payment->amount);
            }

            $payment->update(['status' => 'cancelled']);
        });

        ActivityLog::record('payment.cancelled', $payment, ['reference' => $payment->reference]);
    }

    protected function apply(FeeAssignment $assignment, int $amount): void
    {
        $paid = max(0, min($assignment->amount, $assignment->paid_amount + $amount));

        $assignment->update([
            'paid_amount' => $paid,
            'status' => $paid >= $assignment->amount ? 'paid' : ($paid > 0 ? 'partial' : 'pending'),
        ]);
    }

    protected function createPayment(FeeAssignment $assignment, int $amount, string $method, ?string $paidAt, ?string $transactionRef, ?string $note, string $status): Payment
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                return Payment::create([
                    'reference' => $this->nextReference(),
                    'fee_assignment_id' => $assignment->id,
                    'student_id' => $assignment->student_id,
                    'amount' => $amount,
                    'method' => $method,
                    'transaction_ref' => $transactionRef,
                    'paid_at' => $status === 'paid' ? ($paidAt ?: now()->toDateString()) : null,
                    'status' => $status,
                    'received_by' => auth()->id(),
                    'note' => $note,
                ]);
            } catch (QueryException $e) {
                if ($attempt === 4) {
                    throw $e;
                }
            }
        }

        throw new \RuntimeException('Impossible de générer une référence de reçu.');
    }

    public function nextReference(): string
    {
        $prefix = (string) Setting::get('finance', 'receipt_prefix', 'REC-');
        $next = (int) Payment::withTrashed()->max('id') + 1;

        do {
            $reference = $prefix.str_pad((string) $next++, 5, '0', STR_PAD_LEFT);
        } while (Payment::withTrashed()->where('reference', $reference)->exists());

        return $reference;
    }
}
