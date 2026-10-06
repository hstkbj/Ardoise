<?php

namespace App\Mail;

use App\Models\Central\SubscriptionPayment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Confirmation d'un paiement d'abonnement (FedaPay ou saisi par la plateforme). */
class SubscriptionPaidMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public SubscriptionPayment $payment) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Paiement reçu — abonnement '.config('app.name'));
    }

    public function content(): Content
    {
        $this->payment->loadMissing('tenant', 'plan');
        $tenant = $this->payment->tenant;

        return new Content(
            markdown: 'mail.tenant.subscription-paid',
            with: [
                'adminName' => $tenant->admin_name,
                'schoolName' => $tenant->name,
                'reference' => $this->payment->reference,
                'amount' => number_format($this->payment->amount, 0, ',', ' '),
                'planName' => $this->payment->plan?->name ?? $tenant->plan?->name,
                'expiresAt' => $tenant->expires_at?->format('d/m/Y'),
                'billingUrl' => $tenant->url('/admin/billing'),
            ],
        );
    }
}
