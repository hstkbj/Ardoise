<?php

namespace App\Support;

/** Valeurs par défaut des paramètres d'une école (écran /admin/settings). */
class SettingDefaults
{
    public const SECTIONS = ['identity', 'contact', 'year', 'grading', 'reports', 'notifications', 'notification_rules', 'finance', 'online_payments'];

    public static function for(string $section): array
    {
        return match ($section) {
            'identity' => ['name' => tenant()?->name, 'primary_color' => '#1D5C4D', 'motto' => null, 'logo' => null],
            'contact' => ['address' => null, 'phone' => null, 'email' => null, 'legal_id' => null, 'tax_id' => null],
            'year' => ['period_type' => 'trimester', 'week_days' => ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi']],
            'grading' => ['max_score' => 20, 'pass_mark' => 10, 'rounding' => '0.01', 'lock_after_validation' => 'yes', 'composition_weight' => null],
            'reports' => [
                'show_rank' => 'yes',
                'show_class_average' => 'yes',
                'decisions' => "Félicitations\nEncouragements\nTableau d'honneur\nAvertissement travail\nBlâme",
                // En-tête et pied du bulletin (voir App\Support\ReportCardHeader)
                'header_lines' => null,
                'header_logo' => 'left',
                'second_logo' => null,
                'header_show' => ['motto', 'address', 'phone'],
                'header_title' => 'Bulletin de notes',
                'show_student_photo' => 'yes',
                'signature_title' => 'Le chef d’établissement',
                'signatory_name' => null,
                'signature' => null,
                'stamp' => null,
            ],
            'notifications' => ['absence_notify' => 'immediate', 'sms_sender' => 'ARDOISE'],
            'notification_rules' => ['rules' => NotificationEvents::defaults()],
            'online_payments' => ['enabled' => 'no', 'environment' => 'sandbox', 'public_key' => null, 'secret_key' => null, 'webhook_secret' => null],
            'finance' => ['currency' => 'XOF', 'receipt_prefix' => 'REC-', 'methods' => ['Espèces', 'Mobile money', 'Virement'], 'late_after_days' => 30, 'report_card_unpaid_rule' => 'off', 'report_card_unpaid_days' => 30],
            default => [],
        };
    }
}
