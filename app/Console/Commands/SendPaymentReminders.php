<?php

namespace App\Console\Commands;

use App\Models\Central\Tenant;
use App\Models\Tenant\FeeAssignment;
use App\Services\Notifier;
use App\Tenancy\TenantManager;
use Illuminate\Console\Command;
use Throwable;

class SendPaymentReminders extends Command
{
    protected $signature = 'payments:send-reminders';

    protected $description = 'Envoie les rappels des échéances de frais scolaires';

    public function handle(TenantManager $tenancy, Notifier $notifier): int
    {
        $today = now()->startOfDay();
        $reminders = 0;

        Tenant::whereNotIn('status', ['suspended', 'cancelled'])->each(function (Tenant $tenant) use ($tenancy, $notifier, $today, &$reminders): void {
            try {
                $tenancy->run($tenant, function () use ($notifier, $today, &$reminders): void {
                    FeeAssignment::with(['fee', 'student'])
                        ->where('status', '!=', 'paid')
                        ->whereNotNull('due_date')
                        ->where(function ($query) use ($today): void {
                            $query->where(function ($query) use ($today): void {
                                $query->whereNull('due_reminder_sent_at')
                                    ->whereDate('due_date', '>=', $today->toDateString())
                                    ->whereDate('due_date', '<=', $today->copy()->addDays(3)->toDateString());
                            })->orWhere(function ($query) use ($today): void {
                                $query->whereNull('overdue_reminder_sent_at')
                                    ->whereDate('due_date', '<', $today->toDateString());
                            });
                        })
                        ->chunkById(100, function ($assignments) use ($notifier, $today, &$reminders): void {
                            foreach ($assignments as $assignment) {
                                if (! $assignment->student || ! $assignment->fee) {
                                    continue;
                                }

                                $feeName = $assignment->fee->name.($assignment->fee->installments > 1 ? ' (tranche '.$assignment->installment_no.')' : '');
                                $remaining = number_format($assignment->remaining(), 0, ',', ' ');
                                $dueDate = $assignment->due_date->format('d/m/Y');

                                if ($assignment->due_date->greaterThanOrEqualTo($today)) {
                                    $notifier->event(
                                        'payment.due_reminder',
                                        'Échéance de paiement à venir',
                                        "{$feeName} : {$remaining} FCFA à régler avant le {$dueDate}.",
                                        $assignment->student,
                                        ['parent' => '/parent/payments'],
                                    );
                                    $assignment->forceFill(['due_reminder_sent_at' => now()])->save();
                                } else {
                                    $notifier->event(
                                        'payment.overdue',
                                        'Échéance de paiement en retard',
                                        "{$feeName} : {$remaining} FCFA restent à régler depuis le {$dueDate}.",
                                        $assignment->student,
                                        ['parent' => '/parent/payments'],
                                    );
                                    $assignment->forceFill(['overdue_reminder_sent_at' => now()])->save();
                                }

                                $reminders++;
                            }
                        });
                });
            } catch (Throwable $exception) {
                $this->warn($tenant->code.' : '.$exception->getMessage());
            }
        });

        $this->info("{$reminders} rappel(s) d'échéance envoyé(s).");

        return self::SUCCESS;
    }
}
