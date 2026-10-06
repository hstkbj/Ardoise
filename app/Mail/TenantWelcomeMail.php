<?php

namespace App\Mail;

use App\Models\Central\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Identifiants envoyés à l'administrateur d'une nouvelle école.
 * Le message contient un mot de passe : il est chiffré dans la file d'attente.
 */
class TenantWelcomeMail extends Mailable implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Tenant $tenant,
        public string $password,
        public bool $isReset = false,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->isReset
                ? 'Vos nouveaux identifiants '.config('app.name').' — '.$this->tenant->name
                : 'Bienvenue sur '.config('app.name').' — accès de '.$this->tenant->name,
        );
    }

    public function content(): Content
    {
        $this->tenant->loadMissing('plan');

        return new Content(
            markdown: 'mail.tenant.welcome',
            with: [
                'adminName' => $this->tenant->admin_name,
                'schoolName' => $this->tenant->name,
                'loginUrl' => $this->tenant->url('/login'),
                'domain' => $this->tenant->primaryDomain(),
                'schoolCode' => $this->tenant->code,
                'email' => $this->tenant->admin_email,
                'password' => $this->password,
                'planName' => $this->tenant->plan?->name,
                'trialEndsAt' => $this->tenant->status === 'trial' ? $this->tenant->billingEndsAt()?->format('d/m/Y') : null,
                'expiresAt' => $this->tenant->status !== 'trial' ? $this->tenant->expires_at?->format('d/m/Y') : null,
            ],
        );
    }
}
