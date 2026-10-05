<?php

namespace App\Support;

/** Catalogue RBAC « ressource.action » (identique à resources/js/config/permissions.js). */
final class PermissionCatalog
{
    public const GROUPS = [
        'schools' => ['Établissements', ['view', 'create', 'update', 'delete']],
        'academic_years' => ['Années scolaires', ['view', 'create', 'update']],
        'classes' => ['Classes', ['view', 'create', 'update', 'delete']],
        'students' => ['Élèves', ['view', 'create', 'update', 'delete', 'export']],
        'parents' => ['Parents', ['view', 'create', 'update', 'delete']],
        'teachers' => ['Enseignants', ['view', 'create', 'update', 'delete']],
        'subjects' => ['Matières', ['view', 'create', 'update', 'delete']],
        'assessments' => ['Évaluations', ['view', 'create', 'update', 'delete']],
        'grades' => ['Notes', ['view', 'create', 'update', 'publish']],
        'report_cards' => ['Bulletins', ['view', 'create', 'publish', 'export']],
        'attendance' => ['Absences', ['view', 'create', 'update']],
        'timetable' => ['Emploi du temps', ['view', 'create', 'update', 'delete']],
        'homework' => ['Devoirs', ['view', 'create', 'update', 'delete', 'publish']],
        'announcements' => ['Annonces', ['view', 'create', 'update', 'delete', 'publish']],
        'fees' => ['Frais scolaires', ['view', 'create', 'update', 'delete']],
        'payments' => ['Paiements', ['view', 'create', 'update', 'export']],
        'documents' => ['Documents', ['view', 'create', 'delete']],
        'users' => ['Utilisateurs', ['view', 'create', 'update', 'delete']],
        'roles' => ['Rôles', ['view', 'create', 'update', 'delete']],
        'settings' => ['Paramètres', ['view', 'update']],
    ];

    public const ACTION_LABELS = [
        'view' => 'Consulter', 'create' => 'Créer', 'update' => 'Modifier',
        'delete' => 'Supprimer', 'publish' => 'Publier', 'export' => 'Exporter',
    ];

    public const ROLES = [
        'school_admin' => ['Administrateur', "Accès complet à l'organisation"],
        'director' => ['Directeur', 'Pilotage, validation des notes et bulletins'],
        'academic_manager' => ['Responsable pédagogique', 'Évaluations, notes, emplois du temps'],
        'teacher' => ['Enseignant', 'Ses classes, ses évaluations, ses notes'],
        'accountant' => ['Comptable', 'Frais, paiements, reçus'],
        'secretary' => ['Secrétariat', 'Inscriptions, documents'],
        'parent' => ['Parent', 'Suivi de ses enfants'],
        'student' => ['Élève', 'Consultation de ses résultats'],
    ];

    /** @return array<int, array{key: string, group: string, label: string}> */
    public static function all(): array
    {
        $out = [];

        foreach (self::GROUPS as $key => [$label, $actions]) {
            foreach ($actions as $action) {
                $out[] = ['key' => "{$key}.{$action}", 'group' => $key, 'label' => self::ACTION_LABELS[$action].' — '.mb_strtolower($label)];
            }
        }

        return $out;
    }

    /** @return array<string, array<int, string>> */
    public static function defaultGrants(): array
    {
        $all = fn (string $g) => array_map(fn ($a) => "{$g}.{$a}", self::GROUPS[$g][1]);
        $view = fn (string ...$g) => array_map(fn ($x) => "{$x}.view", $g);

        return [
            'school_admin' => ['*'],
            'director' => array_merge(
                $view('schools', 'academic_years', 'classes', 'students', 'parents', 'teachers', 'subjects', 'fees', 'payments', 'documents', 'users'),
                $all('assessments'), $all('grades'), $all('report_cards'), $all('attendance'), $all('announcements'), $all('timetable'), $all('homework'),
            ),
            'academic_manager' => array_merge(
                $view('classes', 'students', 'teachers'),
                $all('subjects'), $all('assessments'), $all('grades'), $all('timetable'), $all('homework'),
                ['report_cards.view', 'report_cards.create', 'attendance.view'],
            ),
            'teacher' => array_merge(
                $view('classes', 'students', 'timetable'),
                ['assessments.view', 'assessments.create', 'assessments.update', 'grades.view', 'grades.create', 'grades.update', 'attendance.view', 'attendance.create'],
                $all('homework'),
            ),
            'accountant' => array_merge($view('students', 'parents'), $all('fees'), $all('payments'), ['documents.view']),
            'secretary' => array_merge($view('classes', 'payments'), $all('students'), $all('parents'), $all('documents'), ['attendance.view', 'attendance.update']),
            'parent' => [],
            'student' => [],
        ];
    }
}
