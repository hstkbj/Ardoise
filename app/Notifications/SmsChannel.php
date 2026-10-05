<?php

namespace App\Notifications;

use App\Services\Sms\SmsGateway;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Cache;

class SmsChannel
{
    public function __construct(protected SmsGateway $gateway) {}

    public function send(object $notifiable, Notification $notification): void
    {
        $to = $notifiable->routeNotificationFor('sms', $notification);

        if (! $to || ! method_exists($notification, 'toSms')) {
            return;
        }

        if ($this->gateway->send($to, $notification->toSms($notifiable)) && $tenant = tenant()) {
            // Compteur d'utilisation (facturation / statistiques plateforme)
            Cache::increment('sms-count:'.$tenant->id.':'.now()->format('Y-m'));
        }
    }
}
