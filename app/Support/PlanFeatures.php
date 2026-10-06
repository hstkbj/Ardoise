<?php

namespace App\Support;

/**
 * Modules activables par plan d'abonnement.
 *
 * Le socle (élèves, classes, enseignants, matières, utilisateurs, annonces,
 * paramètres) est toujours inclus ; les modules ci-dessous dépendent du plan.
 */
final class PlanFeatures
{
    /** @var array<string, string> clé => libellé */
    public const MODULES = [
        'grades' => 'Notes et bulletins',
        'attendance' => 'Absences et retards',
        'parent_portal' => 'Espace parent (application)',
        'homework' => 'Devoirs',
        'timetable' => 'Emplois du temps',
        'finance' => 'Frais et paiements',
        'online_payments' => 'Paiement en ligne des frais (FedaPay)',
        'sms' => 'Notifications SMS',
        'documents' => 'Documents',
        'analytics' => 'Tableaux de bord consolidés',
        'priority_support' => 'Support prioritaire',
    ];

    /** Anciens libellés libres des plans → modules. */
    private const LEGACY = [
        'Notes et bulletins' => ['grades'],
        'Absences' => ['attendance'],
        'Espace parent' => ['parent_portal', 'homework', 'documents'],
        'Tout Essentiel' => ['grades', 'attendance', 'parent_portal', 'homework', 'documents'],
        'Frais et paiements' => ['finance', 'online_payments'],
        'Emplois du temps' => ['timetable'],
        'Notifications SMS' => ['sms'],
        'Tout Établissement' => ['grades', 'attendance', 'parent_portal', 'homework', 'documents', 'finance', 'online_payments', 'timetable', 'sms'],
        'Tableaux consolidés' => ['analytics'],
        'Support prioritaire' => ['priority_support'],
    ];

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::MODULES);
    }

    public static function exists(string $key): bool
    {
        return isset(self::MODULES[$key]);
    }

    /**
     * Convertit une liste de libellés (anciens plans) ou de clés en clés de modules.
     *
     * @param  array<int, string>  $features
     * @return list<string>
     */
    public static function fromLegacy(array $features): array
    {
        $keys = [];

        foreach ($features as $feature) {
            if (self::exists($feature)) {
                $keys[] = $feature;
            } else {
                array_push($keys, ...(self::LEGACY[$feature] ?? []));
            }
        }

        return array_values(array_intersect(self::keys(), array_unique($keys)));
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(fn (string $key, string $label) => ['value' => $key, 'label' => $label], self::keys(), self::MODULES);
    }
}
