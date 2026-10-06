import { reactive } from 'vue';

/**
 * Listes de référence des filtres et formulaires.
 * Les listes dynamiques (établissements, classes, matières, enseignants,
 * élèves, frais, rôles…) sont remplies par l'API au chargement (stores/options).
 */
export const OPTIONS = reactive({
  schools: [],
  classes: [],
  academicYears: [],
  terms: [],
  subjects: [],
  teachers: [],
  students: [],
  fees: [],
  roles: [],
  levels: ['CP', 'CE1', 'CE2', 'CM1', 'CM2', '6e', '5e', '4e', '3e', '2nde', '1re', 'Tle'].map((l) => ({ value: l, label: l })),
  genders: [
    { value: 'F', label: 'Féminin' },
    { value: 'M', label: 'Masculin' },
  ],
  assessmentTypes: [
    { value: 'devoir', label: 'Devoir' },
    { value: 'interrogation', label: 'Interrogation' },
    { value: 'examen', label: 'Examen' },
    { value: 'composition', label: 'Composition' },
    { value: 'controle_continu', label: 'Contrôle continu' },
    { value: 'oral', label: 'Oral' },
  ],
  contracts: [
    { value: 'permanent', label: 'Permanent' },
    { value: 'vacataire', label: 'Vacataire' },
  ],
  feeCategories: [
    { value: 'scolarite', label: 'Scolarité' },
    { value: 'inscription', label: 'Inscription' },
    { value: 'cantine', label: 'Cantine' },
    { value: 'transport', label: 'Transport' },
    { value: 'autre', label: 'Autre' },
  ],
  paymentMethods: ['Espèces', 'Mobile money', 'Virement', 'Chèque', 'Carte'].map((m) => ({ value: m, label: m })),
  documentCategories: [
    { value: 'eleve', label: 'Documents élèves' },
    { value: 'enseignant', label: 'Documents enseignants' },
    { value: 'administratif', label: 'Administratif' },
    { value: 'certificat', label: 'Certificats' },
    { value: 'justificatif', label: 'Justificatifs' },
  ],
  audiences: [
    { value: 'school', label: 'Établissement' },
    { value: 'class', label: 'Classe' },
    { value: 'teachers', label: 'Enseignants' },
    { value: 'parents', label: 'Parents' },
    { value: 'students', label: 'Élèves' },
  ],
  channels: [
    { value: 'in_app', label: 'In-app' },
    { value: 'email', label: 'E-mail' },
    { value: 'sms', label: 'SMS' },
    { value: 'push', label: 'Push' },
  ],
  priorities: [
    { value: 'low', label: 'Basse' },
    { value: 'normal', label: 'Normale' },
    { value: 'high', label: 'Haute' },
  ],
  periods: [
    { value: 'monthly', label: 'Mensuel' },
    { value: 'yearly', label: 'Annuel' },
  ],
  // Modules activables par plan (clés identiques à App\Support\PlanFeatures)
  modules: [
    { value: 'grades', label: 'Notes et bulletins' },
    { value: 'attendance', label: 'Absences et retards' },
    { value: 'parent_portal', label: 'Espace parent (application)' },
    { value: 'homework', label: 'Devoirs' },
    { value: 'timetable', label: 'Emplois du temps' },
    { value: 'finance', label: 'Frais et paiements' },
    { value: 'online_payments', label: 'Paiement en ligne des frais (FedaPay)' },
    { value: 'sms', label: 'Notifications SMS' },
    { value: 'documents', label: 'Documents' },
    { value: 'analytics', label: 'Tableaux de bord consolidés' },
    { value: 'priority_support', label: 'Support prioritaire' },
  ],
  plans: ['Essentiel', 'Établissement', 'Groupe scolaire'].map((p) => ({ value: p, label: p })),
  allRoles: [
    { value: 'school_admin', label: 'Administrateur' },
    { value: 'director', label: 'Directeur' },
    { value: 'academic_manager', label: 'Responsable pédagogique' },
    { value: 'teacher', label: 'Enseignant' },
    { value: 'accountant', label: 'Comptable' },
    { value: 'secretary', label: 'Secrétariat' },
    { value: 'parent', label: 'Parent' },
  ],
});

export function optionLabel(listKey, value) {
  return OPTIONS[listKey]?.find((o) => String(o.value) === String(value))?.label ?? value;
}
