<?php

namespace App\Notifications;

use App\Models\Tenant\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification générique de l'école.
 * Toujours enregistrée en base (in-app) ; envoyée aussi par SMS / e-mail si
 * le canal est activé dans les paramètres et que le destinataire est joignable.
 */
class SchoolNotification extends Notification
{
    use Queueable;

    /**
     * @param  string  $type  parent | teacher | admin | system
     * @param  bool  $urgent  envoie un SMS si le canal est actif (absences, bulletins…)
     */
    public function __construct(
        public string $title,
        public string $body,
        public string $type = 'parent',
        public ?string $link = null,
        public bool $urgent = false,
    ) {}

    public function via(object $notifiable): array
    {
        $enabled = (array) Setting::get('notifications', 'channels', ['in_app']);
        $channels = ['database'];

        if ($this->urgent && in_array('sms', $enabled, true) && $notifiable->routeNotificationFor('sms')) {
            $channels[] = SmsChannel::class;
        }

        if (in_array('email', $enabled, true) && ! empty($notifiable->email)) {
            $channels[] = 'mail';
        }

        return $channels;
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
        return (new MailMessage)->subject($this->title)->line($this->body);
    }
}
