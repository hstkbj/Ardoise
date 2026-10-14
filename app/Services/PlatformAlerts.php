<?php

namespace App\Services;

use App\Models\Central\PlatformAdmin;
use App\Models\Central\PlatformSetting;
use App\Models\Central\Tenant;
use App\Notifications\Platform\PlatformAlert;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Alertes des superadmins. Réglages (console → Paramètres → Alertes) :
 *  - events : événements à signaler (tous par défaut) ;
 *  - mail   : envoi par e-mail en plus de la console (oui par défaut) ;
 *  - emails : adresses supplémentaires (comptabilité, astreinte…), séparées par des virgules.
 */
class PlatformAlerts
{
    public const EVENTS = [
        'subscription.paid' => 'Paiement d’abonnement reçu',
        'subscription.payment_failed' => 'Paiement d’abonnement échoué',
        'subscription.ending' => 'Abonnement bientôt expiré',
        'subscription.grace' => 'Abonnement en délai de grâce',
        'subscription.expired' => 'Abonnement expiré (école bloquée)',
        'backup.failed' => 'Échec de sauvegarde',
    ];

    public function send(string $event, string $title, string $body, ?string $link = null, string $level = 'info'): void
    {
        $settings = PlatformSetting::section('alerts');
        $enabled = $settings['events'] ?? array_keys(self::EVENTS);

        if (! in_array($event, (array) $enabled, true)) {
            return;
        }

        $channels = PlatformSetting::flag('alerts', 'mail', true) ? ['database', 'mail'] : ['database'];
        $alert = new PlatformAlert($event, $title, $body, $link, $level, $channels);

        try {
            Notification::send(PlatformAdmin::all(), $alert);

            $extra = array_filter(array_map('trim', explode(',', (string) ($settings['emails'] ?? ''))), fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL));

            if ($extra && in_array('mail', $channels, true)) {
                Notification::route('mail', array_values($extra))->notify($alert);
            }
        } catch (Throwable $e) {
            // Une alerte ne doit jamais faire échouer un paiement ou une tâche planifiée
            Log::error('Alerte plateforme non envoyée : '.$e->getMessage(), ['event' => $event]);
        }
    }

    /** N'envoie l'alerte qu'une fois pour une clé donnée (mémorisée dans tenants.data). */
    public function once(Tenant $tenant, string $key, string $event, string $title, string $body, ?string $link = null, string $level = 'info'): bool
    {
        $data = $tenant->data ?? [];

        if (in_array($key, $data['platform_alerts'] ?? [], true)) {
            return false;
        }

        $this->send($event, $title, $body, $link, $level);
        $data['platform_alerts'] = array_slice([...($data['platform_alerts'] ?? []), $key], -30);
        $tenant->forceFill(['data' => $data])->save();

        return true;
    }
}
