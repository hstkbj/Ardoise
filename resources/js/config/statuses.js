/**
 * Statuts affichés par <StatusBadge>. Les clés sont les valeurs envoyées
 * par l'API Laravel. tone : success | warning | danger | info | neutral
 */
export const STATUSES = {
  // Génériques
  active: { label: 'Actif', tone: 'success' },
  inactive: { label: 'Inactif', tone: 'neutral' },
  pending: { label: 'En attente', tone: 'warning' },
  archived: { label: 'Archivé', tone: 'neutral' },
  suspended: { label: 'Suspendu', tone: 'danger' },
  invited: { label: 'Invité', tone: 'info' },
  draft: { label: 'Brouillon', tone: 'warning' },
  published: { label: 'Publié', tone: 'success' },
  closed: { label: 'Clôturé', tone: 'neutral' },
  upcoming: { label: 'À venir', tone: 'info' },

  // Élèves / enseignants
  transferred: { label: 'Transféré', tone: 'info' },
  on_leave: { label: 'En congé', tone: 'warning' },

  // Évaluations / notes
  validated: { label: 'Validée', tone: 'info' },
  locked: { label: 'Verrouillée', tone: 'neutral' },
  entered: { label: 'Saisie', tone: 'success' },
  missing: { label: 'À saisir', tone: 'neutral' },
  absent: { label: 'Absent', tone: 'warning' },
  error: { label: 'Erreur', tone: 'danger' },

  // Présences
  present: { label: 'Présent', tone: 'success' },
  late: { label: 'Retard', tone: 'info' },
  excused: { label: 'Justifiée', tone: 'neutral' },

  // Paiements
  paid: { label: 'Payé', tone: 'success' },
  partial: { label: 'Partiel', tone: 'info' },
  overdue: { label: 'En retard', tone: 'danger' },
  failed: { label: 'Échoué', tone: 'danger' },
  cancelled: { label: 'Annulé', tone: 'neutral' },

  // Abonnements SaaS
  trial: { label: 'Essai', tone: 'info' },
  expired: { label: 'Expiré', tone: 'danger' },

  // Support
  open: { label: 'Ouvert', tone: 'warning' },
  in_progress: { label: 'En cours', tone: 'info' },
  resolved: { label: 'Résolu', tone: 'success' },
};

export function statusOptions(keys) {
  return keys.map((k) => ({ value: k, label: STATUSES[k]?.label ?? k }));
}
