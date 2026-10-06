<?php

namespace App\Support;

use App\Models\Tenant\Setting;

/**
 * Événements qui déclenchent une notification, et règles par défaut
 * (destinataires + canaux). Chaque école ajuste ces règles dans
 * Paramètres → Notifications ; elles sont stockées dans la section
 * « notification_rules ».
 *
 * Les annonces ne sont pas concernées : leurs destinataires et canaux
 * sont choisis à chaque publication.
 */
final class NotificationEvents
{
    /** @var array<string, array{label: string, group: string, type: string, recipients: list<string>, channels: list<string>}> */
    public const EVENTS = [
        'attendance.recorded' => ['label' => 'Absence ou retard signalé', 'group' => 'Vie scolaire', 'type' => 'attendance', 'recipients' => ['parents'], 'channels' => ['in_app', 'sms']],
        'attendance.justified' => ['label' => 'Justificatif envoyé par un parent', 'group' => 'Vie scolaire', 'type' => 'attendance', 'recipients' => ['secretary', 'director', 'head_teacher'], 'channels' => ['in_app']],
        'grades.published' => ['label' => 'Notes validées', 'group' => 'Pédagogie', 'type' => 'grade', 'recipients' => ['parents'], 'channels' => ['in_app']],
        'report_card.published' => ['label' => 'Bulletin publié', 'group' => 'Pédagogie', 'type' => 'report_card', 'recipients' => ['parents'], 'channels' => ['in_app', 'email', 'sms']],
        'homework.published' => ['label' => 'Nouveau devoir', 'group' => 'Pédagogie', 'type' => 'homework', 'recipients' => ['parents'], 'channels' => ['in_app']],
        'payment.received' => ['label' => 'Paiement encaissé (reçu au parent)', 'group' => 'Finances', 'type' => 'payment', 'recipients' => ['parents'], 'channels' => ['in_app', 'email']],
        'payment.online_received' => ['label' => 'Paiement en ligne reçu (FedaPay)', 'group' => 'Finances', 'type' => 'payment', 'recipients' => ['accountant'], 'channels' => ['in_app']],
        'payment.online_pending' => ['label' => 'Paiement à confirmer par la comptabilité', 'group' => 'Finances', 'type' => 'payment', 'recipients' => ['accountant', 'school_admin'], 'channels' => ['in_app']],
        'document.requested' => ['label' => 'Demande de document d’un parent', 'group' => 'Secrétariat', 'type' => 'document', 'recipients' => ['secretary', 'school_admin'], 'channels' => ['in_app', 'email']],
    ];

    /**
     * Destinataires possibles. « parents », « head_teacher » et « class_teachers »
     * sont relatifs aux élèves concernés ; les autres sont des rôles de l'école.
     *
     * @var array<string, string>
     */
    public const RECIPIENTS = [
        'parents' => 'Parents de l’élève',
        'head_teacher' => 'Professeur principal',
        'class_teachers' => 'Enseignants de la classe',
        'school_admin' => 'Administrateurs',
        'director' => 'Direction',
        'academic_manager' => 'Responsables pédagogiques',
        'accountant' => 'Comptabilité',
        'secretary' => 'Secrétariat',
    ];

    /** @var array<string, string> */
    public const CHANNELS = [
        'in_app' => 'Application',
        'email' => 'E-mail',
        'sms' => 'SMS',
    ];

    /** @return array<string, array{recipients: list<string>, channels: list<string>}> */
    public static function defaults(): array
    {
        return array_map(fn (array $event) => ['recipients' => $event['recipients'], 'channels' => $event['channels']], self::EVENTS);
    }

    /**
     * Règles de l'école (règles enregistrées fusionnées avec les valeurs par défaut).
     *
     * @return array<string, array{recipients: list<string>, channels: list<string>}>
     */
    public static function rules(): array
    {
        $saved = (array) Setting::get('notification_rules', 'rules', []);
        $rules = [];

        foreach (self::defaults() as $event => $default) {
            $rule = is_array($saved[$event] ?? null) ? $saved[$event] : [];
            $rules[$event] = [
                'recipients' => self::clean($rule['recipients'] ?? $default['recipients'], self::RECIPIENTS),
                'channels' => self::clean($rule['channels'] ?? $default['channels'], self::CHANNELS),
            ];
        }

        return $rules;
    }

    /** @return array{recipients: list<string>, channels: list<string>} */
    public static function rule(string $event): array
    {
        return self::rules()[$event] ?? ['recipients' => [], 'channels' => []];
    }

    /** Catalogue envoyé à l'écran de paramétrage. */
    public static function catalog(): array
    {
        $options = fn (array $list) => array_map(fn ($key, $label) => ['value' => $key, 'label' => $label], array_keys($list), $list);

        return [
            'events' => array_map(fn ($key, $event) => ['key' => $key, 'label' => $event['label'], 'group' => $event['group']], array_keys(self::EVENTS), self::EVENTS),
            'recipients' => $options(self::RECIPIENTS),
            'channels' => $options(self::CHANNELS),
        ];
    }

    /**
     * @param  array<string, string>  $allowed
     * @return list<string>
     */
    protected static function clean(mixed $values, array $allowed): array
    {
        return array_values(array_intersect(array_keys($allowed), (array) $values));
    }
}
