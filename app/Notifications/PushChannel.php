<?php

namespace App\Notifications;

use App\Services\Push\ExpoPush;
use Illuminate\Notifications\Notification;

/** Canal « push » : téléphones enregistrés par l'application parents (voir App\Models\Tenant\PushToken). */
class PushChannel
{
    public function __construct(protected ExpoPush $expo) {}

    public function send(object $notifiable, Notification $notification): void
    {
        $tokens = (array) $notifiable->routeNotificationFor('push', $notification);

        if ($tokens === [] || ! method_exists($notification, 'toPush')) {
            return;
        }

        $this->expo->send($tokens, $notification->toPush($notifiable));
    }
}
