<?php

namespace App\Mail;

use App\Models\Central\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Rappel de renouvellement envoyé à l'administrateur de l'école.
 *
 * kind : ending (fin proche) | grace (échéance passée, délai de grâce) | expired (accès bloqué)
 */
class SubscriptionReminderMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Tenant $tenant, public string $kind) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: match ($this->kind) {
            'expired' => 'Accès suspendu : abonnement de '.$this->tenant->name.' à renouveler',
            'grace' => 'Abonnement expiré : renouvelez avant le '.$this->tenant->graceEndsAt()?->format('d/m/Y'),
            default => 'Votre abonnement '.config('app.name').' arrive à échéance',
        });
    }

    public function content(): Content
    {
        $isTrial = $this->tenant->status === 'trial';

        return new Content(
            markdown: 'mail.tenant.subscription-reminder',
            with: [
                'kind' => $this->kind,
                'adminName' => $this->tenant->admin_name,
                'schoolName' => $this->tenant->name,
                'period' => $isTrial ? 'période d’essai' : 'abonnement',
                'endsAt' => $this->tenant->billingEndsAt()?->format('d/m/Y'),
                'daysLeft' => max(0, (int) ceil(now()->diffInDays($this->tenant->billingEndsAt(), false))),
                'graceEndsAt' => $this->tenant->graceEndsAt()?->format('d/m/Y'),
                'billingUrl' => $this->tenant->url('/admin/billing'),
            ],
        );
    }
}
