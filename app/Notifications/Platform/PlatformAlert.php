<?php

namespace App\Notifications\Platform;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Alerte envoyée aux superadmins : paiement d'abonnement reçu ou échoué,
 * abonnement bientôt expiré / expiré, échec de sauvegarde.
 * Canaux : console (cloche) et e-mail, selon Paramètres → Alertes.
 */
class PlatformAlert extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param  array<int, string>  $channels */
    public function __construct(
        public string $event,
        public string $title,
        public string $body,
        public ?string $link = null,
        public string $level = 'info',
        public array $channels = ['database', 'mail'],
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        // Destinataires e-mail supplémentaires (Notification::route) : pas de base de données
        return $notifiable instanceof AnonymousNotifiable
            ? array_values(array_intersect($this->channels, ['mail']))
            : $this->channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('['.config('app.name').'] '.$this->title)
            ->line($this->body);

        if ($this->level === 'error') {
            $message->error();
        }

        return $this->link
            ? $message->action('Ouvrir la console', url($this->link))
            : $message;
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'type' => $this->event,
            'level' => $this->level,
            'link' => $this->link,
        ];
    }
}
