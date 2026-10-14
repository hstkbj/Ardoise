<?php

namespace App\Support;

/** Libellés du journal d'activité (actions et types d'objets). */
final class ActivityLabels
{
    public const ACTIONS = [
        'login' => 'Connexion',
        'login.recovery_code' => 'Connexion avec un code de secours',
        'parent.login' => 'Connexion d’un parent',
        'security.password_changed' => 'Mot de passe modifié',
        'security.two_factor_enabled' => 'Double authentification activée',
        'security.two_factor_disabled' => 'Double authentification désactivée',
        'security.recovery_codes_regenerated' => 'Codes de secours régénérés',
        'academic_year.activated' => 'Année scolaire activée',
        'academic_year.closed' => 'Année scolaire clôturée',
        'assessment.created' => 'Évaluation créée',
        'assessment.deleted' => 'Évaluation supprimée',
        'assessment.validated' => 'Notes validées',
        'assessment.unlocked' => 'Notes déverrouillées',
        'campus.created' => 'Établissement créé',
        'campus.deleted' => 'Établissement supprimé',
        'class.created' => 'Classe créée',
        'class.deleted' => 'Classe supprimée',
        'fee.created' => 'Frais créé',
        'parent.created' => 'Parent ajouté',
        'parent.deleted' => 'Parent supprimé',
        'parent.code_regenerated' => 'Code d’accès parent régénéré',
        'payment.recorded' => 'Paiement encaissé',
        'payment.confirmed' => 'Paiement en ligne confirmé',
        'payment.cancelled' => 'Paiement annulé',
        'report_cards.generated' => 'Bulletins générés',
        'report_cards.published' => 'Bulletins publiés',
        'role.permissions_updated' => 'Permissions d’un rôle modifiées',
        'settings.updated' => 'Paramètres modifiés',
        'student.created' => 'Élève inscrit',
        'student.deleted' => 'Élève supprimé',
        'student.archived' => 'Élève archivé',
        'student.transferred' => 'Élève transféré',
        'student.class_changed' => 'Changement de classe',
        'teacher.created' => 'Enseignant ajouté',
        'teacher.deleted' => 'Enseignant supprimé',
        'user.invited' => 'Utilisateur invité',
        'user.updated' => 'Utilisateur modifié',
        'user.deleted' => 'Utilisateur supprimé',
        'user.status_changed' => 'Statut d’un utilisateur modifié',
        'import.students' => 'Import d’élèves',
        'import.grades' => 'Import de notes',
        'import.payments' => 'Import de paiements',
    ];

    /** Groupes proposés dans le filtre (préfixe de l'action). */
    public const GROUPS = [
        'login' => 'Connexions',
        'security' => 'Sécurité des comptes',
        'student' => 'Élèves',
        'parent' => 'Parents',
        'teacher' => 'Enseignants',
        'assessment' => 'Notes et évaluations',
        'report_cards' => 'Bulletins',
        'payment' => 'Paiements',
        'user' => 'Utilisateurs',
        'role' => 'Rôles et permissions',
        'settings' => 'Paramètres',
        'import' => 'Imports',
    ];

    public const SUBJECTS = [
        'Student' => 'Élève',
        'ParentProfile' => 'Parent',
        'Teacher' => 'Enseignant',
        'User' => 'Utilisateur',
        'Assessment' => 'Évaluation',
        'ClassRoom' => 'Classe',
        'Campus' => 'Établissement',
        'AcademicYear' => 'Année scolaire',
        'Fee' => 'Frais',
        'Payment' => 'Paiement',
        'Role' => 'Rôle',
    ];

    /** Clés des détails enregistrés (properties) → libellés. */
    public const PROPERTIES = [
        'amount' => 'montant',
        'fee' => 'frais',
        'references' => 'reçus',
        'reference' => 'reçu',
        'term' => 'période',
        'count' => 'nombre',
        'role' => 'rôle',
        'status' => 'statut',
        'from' => 'de',
        'to' => 'vers',
        'reason' => 'motif',
        'email' => 'e-mail',
        'note' => 'note',
        'section' => 'section',
        'name' => 'nom',
    ];

    public static function property(string $key): string
    {
        return self::PROPERTIES[$key] ?? str_replace('_', ' ', $key);
    }

    public static function action(string $action): string
    {
        return self::ACTIONS[$action] ?? ucfirst(str_replace(['.', '_'], ' ', $action));
    }

    public static function subjectType(?string $class): ?string
    {
        return $class ? (self::SUBJECTS[class_basename($class)] ?? class_basename($class)) : null;
    }
}
