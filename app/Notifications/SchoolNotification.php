<?php

namespace App\Notifications;

use App\Models\Tenant\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification de l'école, envoyée en file d'attente.
 *
 * Canaux demandés par la règle de l'événement : in_app (base), email, sms.
 * Le SMS exige le module « sms » du plan et un numéro ; l'e-mail une adresse.
 */
class SchoolNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  string  $type  attendance | grade | report_card | homework | payment | document | announcement | system
     * @param  list<string>  $channels  in_app | email | sms
     */
    public function __construct(
        public string $title,
        public string $body,
        public string $type = 'system',
        public ?string $link = null,
        public array $channels = ['in_app'],
    ) {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        $via = [];

        if (in_array('in_app', $this->channels, true)) {
            $via[] = 'database';
        }

        if (in_array('email', $this->channels, true) && ! empty($notifiable->email)) {
            $via[] = 'mail';
        }

        if (in_array('sms', $this->channels, true) && tenant()?->hasFeature('sms') && $notifiable->routeNotificationFor('sms')) {
            $via[] = SmsChannel::class;
        }

        return $via;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'type' => $this->type,
            'channel' => 'in_app',
            'link' => $this->link,
        ];
    }

    public function toSms(object $notifiable): string
    {
        $school = Setting::get('identity', 'name') ?? tenant()?->name;

        return mb_substr("{$school} : {$this->title}. {$this->body}", 0, 300);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $school = Setting::get('identity', 'name') ?? tenant()?->name;
        $message = (new MailMessage)
            ->subject($this->title.' — '.$school)
            ->greeting('Bonjour '.$notifiable->name.',')
            ->line($this->body);

        if ($this->link && $tenant = tenant()) {
            $message->action('Ouvrir dans '.config('app.name'), $tenant->url($this->link));
        }

        return $message->salutation($school);
    }
}
