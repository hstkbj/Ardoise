/**
 * Définition déclarative des ressources CRUD.
 *
 * Génère les pages Liste / Création / Édition / Détail (pages/generic) et
 * correspond aux contrôleurs Laravel : `endpoint` = route /api/v1/…,
 * `columns` = attributs de l'API Resource, `form` = champs validés côté serveur.
 *
 * Types de colonne : text | person | status | money | date | datetime | number | option | grade | progress | capacity
 * Types de champ   : text | email | tel | number | date | time | select | textarea | file | checkboxes | color | password
 */
import { statusOptions } from './statuses';

const s = (keys) => ({ options: statusOptions(keys) });

export const RESOURCES = {
  // ---------------------------------------------------------------- École
  schools: {
    endpoint: 'schools',
    permission: 'schools',
    title: 'Établissements',
    singular: 'établissement',
    createLabel: 'Nouvel établissement',
    searchPlaceholder: 'Rechercher un établissement, un code…',
    emptyText: 'Aucun établissement trouvé.',
    titleField: 'name',
    columns: [
      { key: 'name', label: 'Établissement', type: 'person', sub: 'code', sortable: true },
      { key: 'city', label: 'Ville', sortable: true },
      { key: 'manager', label: 'Responsable' },
      { key: 'students_count', label: 'Élèves', type: 'number', sortable: true, align: 'right' },
      { key: 'classes_count', label: 'Classes', type: 'number', align: 'right' },
      { key: 'status', label: 'Statut', type: 'status' },
    ],
    filters: [{ key: 'status', label: 'Statut', ...s(['active', 'inactive']) }],
    rowActions: ['view', 'edit', 'toggle', 'delete'],
    form: [
      {
        title: 'Identité',
        fields: [
          { key: 'name', label: 'Nom', required: true, span: 2 },
          { key: 'code', label: 'Code', required: true, hint: 'Identifiant court, ex. PAL-CCY' },
          { key: 'status', label: 'Statut', type: 'select', ...s(['active', 'inactive']) },
          { key: 'logo', label: 'Logo', type: 'file', accept: 'image/*', span: 2 },
        ],
      },
      {
        title: 'Coordonnées',
        fields: [
          { key: 'address', label: 'Adresse', span: 2 },
          { key: 'city', label: 'Ville' },
          { key: 'phone', label: 'Téléphone', type: 'tel' },
          { key: 'email', label: 'E-mail', type: 'email' },
          { key: 'manager', label: 'Responsable' },
        ],
      },
    ],
    stats: [
      { key: 'students_count', label: 'Élèves' },
      { key: 'classes_count', label: 'Classes' },
      { key: 'teachers_count', label: 'Enseignants' },
    ],
    related: [{ title: 'Classes', resource: 'classes', filterKey: 'school_id', base: '/admin/classes' }],
  },

  'academic-years': {
    endpoint: 'academic-years',
    permission: 'academic_years',
    title: 'Années scolaires',
    singular: 'année scolaire',
    createLabel: 'Nouvelle année',
    emptyText: 'Aucune année scolaire.',
    titleField: 'name',
    columns: [
      { key: 'name', label: 'Année', sortable: true },
      { key: 'starts_on', label: 'Début', type: 'date', sortable: true },
      { key: 'ends_on', label: 'Fin', type: 'date' },
      { key: 'terms_count', label: 'Périodes', type: 'number', align: 'right' },
      { key: 'status', label: 'Statut', type: 'status' },
    ],
    filters: [{ key: 'status', label: 'Statut', ...s(['active', 'upcoming', 'closed']) }],
    rowActions: ['edit', 'set_active', 'close'],
    customActions: {
      set_active: { label: 'Définir comme année active', icon: 'check', confirm: "L'année active est utilisée par défaut dans toutes les pages." },
      close: { label: "Clôturer l'année", icon: 'lock', danger: true, confirm: 'Une année clôturée ne peut plus recevoir de notes ni d’absences.' },
    },
    form: [
      {
        title: 'Année scolaire',
        fields: [
          { key: 'name', label: 'Libellé', required: true, placeholder: '2027-2028' },
          { key: 'terms_count', label: 'Nombre de périodes', type: 'select', options: [{ value: 2, label: '2 (semestres)' }, { value: 3, label: '3 (trimestres)' }], createOnly: true },
          { key: 'starts_on', label: 'Date de début', type: 'date', required: true },
          { key: 'ends_on', label: 'Date de fin', type: 'date', required: true },
        ],
      },
    ],
  },

  classes: {
    endpoint: 'classes',
    permission: 'classes',
    title: 'Classes',
    singular: 'classe',
    createLabel: 'Nouvelle classe',
    searchPlaceholder: 'Rechercher une classe…',
    emptyText: 'Aucune classe trouvée.',
    titleField: 'name',
    columns: [
      { key: 'name', label: 'Classe', type: 'person', sub: 'level', sortable: true },
      { key: 'school_name', label: 'Établissement' },
      { key: 'head_teacher', label: 'Professeur principal' },
      { key: 'students_count', label: 'Effectif', type: 'capacity', align: 'right', sortable: true },
      { key: 'room', label: 'Salle' },
      { key: 'status', label: 'Statut', type: 'status' },
    ],
    filters: [
      { key: 'school_id', label: 'Établissement', optionsKey: 'schools' },
      { key: 'level', label: 'Niveau', optionsKey: 'levels' },
    ],
    form: [
      {
        title: 'Classe',
        fields: [
          { key: 'name', label: 'Nom', required: true, placeholder: '4e B' },
          { key: 'level', label: 'Niveau', type: 'select', optionsKey: 'levels', required: true },
          { key: 'school_id', label: 'Établissement', type: 'select', optionsKey: 'schools', required: true },
          { key: 'academic_year_id', label: 'Année scolaire', type: 'select', optionsKey: 'academicYears' },
          { key: 'head_teacher_id', label: 'Professeur principal', type: 'select', optionsKey: 'teachers' },
          { key: 'capacity', label: 'Capacité', type: 'number' },
          { key: 'room', label: 'Salle' },
          { key: 'status', label: 'Statut', type: 'select', ...s(['active', 'inactive']) },
        ],
      },
    ],
    stats: [
      { key: 'students_count', label: 'Élèves' },
      { key: 'capacity', label: 'Capacité' },
    ],
    related: [{ title: 'Élèves de la classe', resource: 'students', filterKey: 'class_id', base: '/admin/students' }],
  },

  students: {
    endpoint: 'students',
    permission: 'students',
    title: 'Élèves',
    singular: 'élève',
    createLabel: 'Inscrire un élève',
    searchPlaceholder: 'Nom, prénom ou matricule…',
    emptyText: 'Aucun élève trouvé.',
    titleField: 'full_name',
    afterCreate: 'show',
    columns: [
      { key: 'full_name', label: 'Élève', type: 'person', sub: 'matricule', sortable: true },
      { key: 'class_name', label: 'Classe' },
      { key: 'school_name', label: 'Établissement' },
      { key: 'guardian_name', label: 'Parent / tuteur' },
      { key: 'average', label: 'Moyenne', type: 'grade', sortable: true, align: 'right' },
      { key: 'status', label: 'Statut', type: 'status' },
    ],
    filters: [
      { key: 'school_id', label: 'Établissement', optionsKey: 'schools' },
      { key: 'class_id', label: 'Classe', optionsKey: 'classes' },
      { key: 'status', label: 'Statut', ...s(['active', 'transferred', 'archived']) },
    ],
    selectable: true,
    bulkActions: [
      { key: 'transfer', label: 'Transférer' },
      { key: 'archive', label: 'Archiver', danger: true },
    ],
    form: [
      {
        title: 'Informations personnelles',
        fields: [
          { key: 'last_name', label: 'Nom', required: true },
          { key: 'first_name', label: 'Prénoms', required: true },
          { key: 'gender', label: 'Sexe', type: 'select', optionsKey: 'genders' },
          { key: 'birth_date', label: 'Date de naissance', type: 'date' },
          { key: 'birth_place', label: 'Lieu de naissance' },
          { key: 'photo', label: 'Photo', type: 'file', accept: 'image/*' },
        ],
      },
      {
        title: 'Informations scolaires',
        fields: [
          { key: 'matricule', label: 'Matricule', hint: 'Généré automatiquement si vide' },
          { key: 'class_id', label: 'Classe', type: 'select', optionsKey: 'classes', required: true },
          { key: 'enrolled_on', label: "Date d'inscription", type: 'date', createOnly: true },
        ],
      },
      {
        title: 'Parent / tuteur principal',
        description: "Un code d'accès est créé automatiquement pour un nouveau parent : il apparaît sur sa fiche.",
        createOnly: true,
        fields: [
          { key: 'guardian_name', label: 'Nom complet' },
          { key: 'guardian_phone', label: 'Téléphone', type: 'tel', hint: 'Un parent déjà enregistré avec ce numéro est réutilisé' },
          { key: 'guardian_relation', label: 'Lien', type: 'select', options: ['Mère', 'Père', 'Tuteur', 'Autre'].map((v) => ({ value: v, label: v })) },
        ],
      },
    ],
  },

  parents: {
    endpoint: 'parents',
    permission: 'parents',
    title: 'Parents / tuteurs',
    singular: 'parent',
    createLabel: 'Ajouter un parent',
    searchPlaceholder: 'Nom, téléphone ou e-mail…',
    emptyText: 'Aucun parent trouvé.',
    titleField: 'full_name',
    afterCreate: 'show',
    columns: [
      { key: 'full_name', label: 'Parent', type: 'person', sub: 'phone', sortable: true },
      { key: 'children', label: 'Enfants' },
      { key: 'access_code_last4', label: 'Code', type: 'code' },
      { key: 'profession', label: 'Profession' },
      { key: 'status', label: 'Accès', type: 'status' },
    ],
    filters: [{ key: 'status', label: 'Accès', ...s(['active', 'pending', 'inactive']) }],
    rowActions: ['view', 'edit', 'delete'],
    form: [
      {
        title: 'Identité',
        fields: [
          { key: 'last_name', label: 'Nom', required: true },
          { key: 'first_name', label: 'Prénoms', required: true },
          { key: 'phone', label: 'Téléphone', type: 'tel', required: true },
          { key: 'email', label: 'E-mail', type: 'email' },
          { key: 'address', label: 'Adresse', span: 2 },
          { key: 'profession', label: 'Profession' },
        ],
      },
      {
        title: 'Enfants associés',
        description: 'Un parent peut être associé à plusieurs élèves. Il se connecte avec son code d’accès, sans mot de passe.',
        fields: [{ key: 'student_ids', label: 'Élèves', type: 'checkboxes', optionsKey: 'students', span: 2 }],
      },
    ],
  },

  teachers: {
    endpoint: 'teachers',
    permission: 'teachers',
    title: 'Enseignants',
    singular: 'enseignant',
    createLabel: 'Ajouter un enseignant',
    searchPlaceholder: 'Nom, matière…',
    emptyText: 'Aucun enseignant trouvé.',
    titleField: 'full_name',
    columns: [
      { key: 'full_name', label: 'Enseignant', type: 'person', sub: 'email', sortable: true },
      { key: 'subjects', label: 'Matières' },
      { key: 'school_name', label: 'Établissements' },
      { key: 'classes_count', label: 'Classes', type: 'number', align: 'right' },
      { key: 'contract', label: 'Contrat', type: 'option', optionsKey: 'contracts' },
      { key: 'status', label: 'Statut', type: 'status' },
    ],
    filters: [
      { key: 'school_id', label: 'Établissement', optionsKey: 'schools' },
      { key: 'contract', label: 'Contrat', optionsKey: 'contracts' },
      { key: 'status', label: 'Statut', ...s(['active', 'on_leave', 'inactive']) },
    ],
    form: [
      {
        title: 'Informations personnelles',
        description: 'Avec une adresse e-mail, l’enseignant reçoit un lien pour choisir son mot de passe.',
        fields: [
          { key: 'last_name', label: 'Nom', required: true },
          { key: 'first_name', label: 'Prénoms', required: true },
          { key: 'email', label: 'E-mail', type: 'email' },
          { key: 'phone', label: 'Téléphone', type: 'tel' },
        ],
      },
      {
        title: 'Affectation',
        fields: [
          { key: 'subject_ids', label: 'Matières enseignées', type: 'checkboxes', optionsKey: 'subjects', span: 2 },
          { key: 'school_ids', label: 'Établissements', type: 'checkboxes', optionsKey: 'schools', span: 2 },
          { key: 'contract', label: 'Contrat', type: 'select', optionsKey: 'contracts' },
          { key: 'hired_on', label: "Date d'embauche", type: 'date' },
          { key: 'status', label: 'Statut', type: 'select', ...s(['active', 'on_leave', 'inactive']) },
        ],
      },
    ],
  },

  subjects: {
    endpoint: 'subjects',
    permission: 'subjects',
    title: 'Matières',
    singular: 'matière',
    createLabel: 'Nouvelle matière',
    emptyText: 'Aucune matière trouvée.',
    titleField: 'name',
    columns: [
      { key: 'name', label: 'Matière', type: 'person', sub: 'code', sortable: true },
      { key: 'coefficient', label: 'Coef.', type: 'number', align: 'right', sortable: true },
      { key: 'level', label: 'Niveau' },
      { key: 'category', label: 'Catégorie' },
      { key: 'teacher_name', label: 'Enseignant référent' },
      { key: 'status', label: 'Statut', type: 'status' },
    ],
    filters: [{ key: 'status', label: 'Statut', ...s(['active', 'inactive']) }],
    rowActions: ['edit', 'delete'],
    form: [
      {
        title: 'Matière',
        fields: [
          { key: 'name', label: 'Nom', required: true },
          { key: 'code', label: 'Code', required: true },
          { key: 'coefficient', label: 'Coefficient par défaut', type: 'number', required: true },
          { key: 'level', label: 'Cycle', type: 'select', options: ['Primaire', 'Collège', 'Lycée'].map((v) => ({ value: v, label: v })) },
          { key: 'category', label: 'Catégorie' },
          { key: 'teacher_id', label: 'Enseignant référent', type: 'select', optionsKey: 'teachers' },
          { key: 'status', label: 'Statut', type: 'select', ...s(['active', 'inactive']) },
        ],
      },
    ],
  },

  assessments: {
    endpoint: 'assessments',
    permission: 'assessments',
    title: 'Évaluations',
    singular: 'évaluation',
    createLabel: 'Nouvelle évaluation',
    searchPlaceholder: 'Titre, matière, classe…',
    emptyText: 'Aucune évaluation pour ces critères.',
    titleField: 'title',
    columns: [
      { key: 'title', label: 'Évaluation', type: 'person', sub: 'subject_name', sortable: true },
      { key: 'type', label: 'Type', type: 'option', optionsKey: 'assessmentTypes' },
      { key: 'class_name', label: 'Classe' },
      { key: 'date', label: 'Date', type: 'date', sortable: true },
      { key: 'coefficient', label: 'Coef.', type: 'number', align: 'right' },
      { key: 'graded_count', label: 'Notes', type: 'progress', total: 'students_count' },
      { key: 'status', label: 'Statut', type: 'status' },
    ],
    filters: [
      { key: 'type', label: 'Type', optionsKey: 'assessmentTypes' },
      { key: 'class_id', label: 'Classe', optionsKey: 'classes' },
      { key: 'term_id', label: 'Période', optionsKey: 'terms' },
      { key: 'status', label: 'Statut', ...s(['draft', 'validated']) },
    ],
    rowActions: ['view', 'grades', 'edit', 'delete'],
    form: [
      {
        title: 'Évaluation',
        fields: [
          { key: 'title', label: 'Intitulé', required: true, span: 2 },
          { key: 'type', label: 'Type', type: 'select', optionsKey: 'assessmentTypes', required: true },
          { key: 'date', label: 'Date', type: 'date', required: true },
          { key: 'class_id', label: 'Classe', type: 'select', optionsKey: 'classes', required: true },
          { key: 'subject_id', label: 'Matière', type: 'select', optionsKey: 'subjects', required: true },
          { key: 'term_id', label: 'Période', type: 'select', optionsKey: 'terms', hint: 'Par défaut : la période en cours' },
          { key: 'teacher_id', label: 'Enseignant', type: 'select', optionsKey: 'teachers', staffOnly: true },
          { key: 'coefficient', label: 'Coefficient', type: 'number', required: true, default: 1 },
          { key: 'max_score', label: 'Note maximale', type: 'number', required: true, default: 20 },
        ],
      },
    ],
  },

  homework: {
    endpoint: 'homework',
    permission: 'homework',
    title: 'Devoirs',
    singular: 'devoir',
    createLabel: 'Nouveau devoir',
    emptyText: 'Aucun devoir.',
    titleField: 'title',
    columns: [
      { key: 'title', label: 'Devoir', type: 'person', sub: 'subject_name' },
      { key: 'class_name', label: 'Classe' },
      { key: 'teacher_name', label: 'Enseignant' },
      { key: 'due_date', label: 'À rendre le', type: 'date', sortable: true },
      { key: 'attachments_count', label: 'Pièces jointes', type: 'number', align: 'right' },
      { key: 'status', label: 'Statut', type: 'status' },
    ],
    filters: [
      { key: 'class_id', label: 'Classe', optionsKey: 'classes' },
      { key: 'status', label: 'Statut', ...s(['draft', 'published', 'closed']) },
    ],
    form: [
      {
        title: 'Devoir',
        description: 'À la publication, les parents des élèves de la classe sont notifiés.',
        fields: [
          { key: 'title', label: 'Intitulé', required: true, span: 2 },
          { key: 'subject_id', label: 'Matière', type: 'select', optionsKey: 'subjects', required: true },
          { key: 'class_id', label: 'Classe', type: 'select', optionsKey: 'classes', required: true },
          { key: 'due_date', label: 'Date limite', type: 'date', required: true },
          { key: 'status', label: 'Statut', type: 'select', ...s(['draft', 'published', 'closed']), default: 'published' },
          { key: 'instructions', label: 'Consignes', type: 'textarea', span: 2 },
          { key: 'attachments', label: 'Pièces jointes', type: 'file', multiple: true, span: 2 },
        ],
      },
    ],
  },

  announcements: {
    endpoint: 'announcements',
    permission: 'announcements',
    title: 'Annonces',
    singular: 'annonce',
    createLabel: 'Nouvelle annonce',
    emptyText: 'Aucune annonce publiée.',
    titleField: 'title',
    columns: [
      { key: 'title', label: 'Annonce', type: 'person', sub: 'author' },
      { key: 'audience', label: 'Destinataires' },
      { key: 'channels', label: 'Canaux' },
      { key: 'published_at', label: 'Publiée le', type: 'date' },
      { key: 'status', label: 'Statut', type: 'status' },
    ],
    filters: [{ key: 'status', label: 'Statut', ...s(['draft', 'published']) }],
    rowActions: ['edit', 'delete'],
    form: [
      {
        title: 'Contenu',
        fields: [
          { key: 'title', label: 'Titre', required: true, span: 2 },
          { key: 'body', label: 'Message', type: 'textarea', required: true, span: 2, rows: 6 },
        ],
      },
      {
        title: 'Diffusion',
        description: 'Les destinataires reçoivent une notification à la publication.',
        fields: [
          { key: 'audiences', label: 'Publier vers', type: 'checkboxes', optionsKey: 'audiences', span: 2 },
          { key: 'school_id', label: 'Limiter à un établissement', type: 'select', optionsKey: 'schools' },
          { key: 'class_id', label: 'Limiter à une classe', type: 'select', optionsKey: 'classes' },
          { key: 'channels', label: 'Canaux', type: 'checkboxes', optionsKey: 'channels', span: 2 },
          { key: 'status', label: 'Statut', type: 'select', ...s(['draft', 'published']), default: 'published' },
        ],
      },
    ],
  },

  fees: {
    endpoint: 'fees',
    permission: 'fees',
    title: 'Frais scolaires',
    singular: 'frais',
    createLabel: 'Définir des frais',
    emptyText: 'Aucun frais défini.',
    titleField: 'name',
    columns: [
      { key: 'name', label: 'Frais', type: 'person', sub: 'level' },
      { key: 'category', label: 'Catégorie', type: 'option', optionsKey: 'feeCategories' },
      { key: 'amount', label: 'Montant', type: 'money', align: 'right', sortable: true },
      { key: 'installments', label: 'Échéances', type: 'number', align: 'right' },
      { key: 'first_due_date', label: '1re échéance', type: 'date' },
      { key: 'assignments_count', label: 'Échéances élèves', type: 'number', align: 'right' },
      { key: 'status', label: 'Statut', type: 'status' },
    ],
    filters: [{ key: 'category', label: 'Catégorie', optionsKey: 'feeCategories' }],
    rowActions: ['edit', 'delete'],
    form: [
      {
        title: 'Frais',
        description: 'Les échéances de chaque élève concerné sont générées automatiquement.',
        fields: [
          { key: 'name', label: 'Libellé', required: true, span: 2 },
          { key: 'category', label: 'Catégorie', type: 'select', optionsKey: 'feeCategories', required: true },
          { key: 'amount', label: 'Montant total (FCFA)', type: 'number', required: true },
          { key: 'academic_year_id', label: 'Année scolaire', type: 'select', optionsKey: 'academicYears' },
          { key: 'school_id', label: 'Établissement', type: 'select', optionsKey: 'schools', hint: 'Vide = tous les établissements' },
        ],
      },
      {
        title: 'Affectation et échéances',
        fields: [
          { key: 'level_ids', label: 'Niveaux concernés', type: 'checkboxes', optionsKey: 'levels', span: 2, hint: 'Aucun niveau coché = tous les élèves' },
          { key: 'installments', label: "Nombre d'échéances", type: 'number', default: 1 },
          { key: 'first_due_date', label: 'Première échéance', type: 'date' },
          { key: 'interval_months', label: 'Mois entre deux échéances', type: 'number', hint: 'Par défaut : réparties sur l’année' },
          { key: 'status', label: 'Statut', type: 'select', ...s(['active', 'inactive']) },
        ],
      },
    ],
  },

  payments: {
    endpoint: 'payments',
    permission: 'payments',
    title: 'Paiements',
    singular: 'paiement',
    createLabel: 'Enregistrer un paiement',
    searchPlaceholder: 'Reçu, élève, frais…',
    emptyText: 'Aucune échéance trouvée.',
    titleField: 'fee_name',
    columns: [
      { key: 'reference', label: 'Reçu' },
      { key: 'student_name', label: 'Élève', type: 'person', sub: 'class_name' },
      { key: 'fee_name', label: 'Motif' },
      { key: 'amount', label: 'Montant', type: 'money', align: 'right', sortable: true },
      { key: 'paid_amount', label: 'Payé', type: 'money', align: 'right' },
      { key: 'method', label: 'Méthode' },
      { key: 'paid_at', label: 'Payé le', type: 'date', sortable: true },
      { key: 'status', label: 'Statut', type: 'status' },
    ],
    filters: [
      { key: 'status', label: 'Statut', ...s(['paid', 'partial', 'pending', 'overdue']) },
      { key: 'class_id', label: 'Classe', optionsKey: 'classes' },
      { key: 'method', label: 'Méthode', optionsKey: 'paymentMethods' },
      { key: 'has_pending', label: 'En ligne', options: [{ value: '1', label: 'À confirmer' }] },
    ],
    rowActions: ['view', 'receipt'],
    form: [
      {
        title: 'Encaissement',
        description: 'Le montant est réparti automatiquement sur les échéances non soldées, de la plus ancienne à la plus récente.',
        fields: [
          { key: 'student_id', label: 'Élève', type: 'select', optionsKey: 'students', required: true },
          { key: 'fee_id', label: 'Frais', type: 'select', optionsKey: 'fees', required: true },
          { key: 'paid_amount', label: 'Montant reçu (FCFA)', type: 'number', required: true },
          { key: 'method', label: 'Méthode', type: 'select', optionsKey: 'paymentMethods', required: true },
          { key: 'paid_at', label: 'Date', type: 'date', required: true, default: 'today' },
          { key: 'transaction_ref', label: 'Référence de transaction' },
        ],
      },
    ],
  },

  documents: {
    endpoint: 'documents',
    permission: 'documents',
    title: 'Documents',
    singular: 'document',
    createLabel: 'Ajouter un document',
    emptyText: 'Aucun document.',
    titleField: 'name',
    columns: [
      { key: 'name', label: 'Document', type: 'person', sub: 'size' },
      { key: 'category', label: 'Catégorie', type: 'option', optionsKey: 'documentCategories' },
      { key: 'owner', label: 'Concerne' },
      { key: 'uploaded_by', label: 'Ajouté par' },
      { key: 'uploaded_at', label: 'Date', type: 'date', sortable: true },
    ],
    filters: [{ key: 'category', label: 'Catégorie', optionsKey: 'documentCategories' }],
    rowActions: ['download', 'delete'],
    form: [
      {
        title: 'Document',
        fields: [
          { key: 'file', label: 'Fichier', type: 'file', required: true, span: 2 },
          { key: 'category', label: 'Catégorie', type: 'select', optionsKey: 'documentCategories', required: true },
          { key: 'student_id', label: 'Élève concerné', type: 'select', optionsKey: 'students' },
        ],
      },
    ],
  },

  users: {
    endpoint: 'users',
    permission: 'users',
    title: 'Utilisateurs',
    singular: 'utilisateur',
    createLabel: 'Inviter un utilisateur',
    searchPlaceholder: 'Nom ou e-mail…',
    emptyText: 'Aucun utilisateur trouvé.',
    titleField: 'full_name',
    columns: [
      { key: 'full_name', label: 'Utilisateur', type: 'person', sub: 'email', sortable: true },
      { key: 'role', label: 'Rôle', type: 'option', optionsKey: 'allRoles' },
      { key: 'school_name', label: 'Établissements' },
      { key: 'last_login_at', label: 'Dernière connexion', type: 'datetime', sortable: true },
      { key: 'status', label: 'Statut', type: 'status' },
    ],
    filters: [
      { key: 'role', label: 'Rôle', optionsKey: 'allRoles' },
      { key: 'status', label: 'Statut', ...s(['active', 'invited', 'suspended']) },
    ],
    selectable: true,
    bulkActions: [{ key: 'suspend', label: 'Suspendre', danger: true }],
    rowActions: ['edit', 'toggle', 'delete'],
    form: [
      {
        title: 'Utilisateur',
        description: 'L’utilisateur reçoit par e-mail un lien pour choisir son mot de passe.',
        fields: [
          { key: 'full_name', label: 'Nom complet', required: true },
          { key: 'email', label: 'E-mail', type: 'email', required: true },
          { key: 'phone', label: 'Téléphone', type: 'tel' },
          { key: 'role', label: 'Rôle', type: 'select', optionsKey: 'roles', required: true },
          { key: 'school_ids', label: 'Établissements accessibles', type: 'checkboxes', optionsKey: 'schools', span: 2, hint: 'Aucun = tous' },
        ],
      },
    ],
  },

  'support-tickets': {
    endpoint: 'support-tickets',
    title: 'Support',
    singular: 'demande',
    createLabel: 'Nouvelle demande',
    emptyText: 'Aucune demande de support.',
    titleField: 'subject',
    columns: [
      { key: 'id', label: 'N°', type: 'number' },
      { key: 'subject', label: 'Sujet', type: 'person', sub: 'category' },
      { key: 'priority', label: 'Priorité', type: 'option', optionsKey: 'priorities' },
      { key: 'updated_at', label: 'Mise à jour', type: 'datetime', sortable: true },
      { key: 'status', label: 'Statut', type: 'status' },
    ],
    filters: [{ key: 'status', label: 'Statut', ...s(['open', 'in_progress', 'resolved']) }],
    rowActions: ['view'],
    form: [
      {
        title: 'Votre demande',
        fields: [
          { key: 'subject', label: 'Sujet', required: true, span: 2 },
          { key: 'category', label: 'Catégorie', type: 'select', options: ['Élèves', 'Notes', 'Bulletins', 'Paiements', 'Compte', 'Autre'].map((v) => ({ value: v, label: v })) },
          { key: 'priority', label: 'Priorité', type: 'select', optionsKey: 'priorities' },
          { key: 'message', label: 'Description', type: 'textarea', required: true, span: 2, rows: 6 },
        ],
      },
    ],
  },

  // ------------------------------------------------------------- Plateforme
  'platform/tenants': {
    endpoint: 'platform/tenants',
    title: 'Tenants',
    singular: 'tenant',
    createLabel: 'Nouveau tenant',
    searchPlaceholder: 'Nom, code ou domaine…',
    emptyText: 'Aucun tenant.',
    titleField: 'name',
    columns: [
      { key: 'name', label: 'Tenant', type: 'person', sub: 'domain', sortable: true },
      { key: 'code', label: 'Code' },
      { key: 'plan', label: 'Plan' },
      { key: 'users_count', label: 'Utilisateurs', type: 'number', align: 'right' },
      { key: 'students_count', label: 'Élèves', type: 'number', align: 'right' },
      { key: 'created_at', label: 'Créé le', type: 'date', sortable: true },
      { key: 'expires_at', label: 'Expire le', type: 'date', sortable: true },
      { key: 'billing_state', label: 'Abonnement', type: 'status' },
    ],
    filters: [
      { key: 'plan', label: 'Plan', optionsKey: 'plans' },
      { key: 'status', label: 'Statut', ...s(['active', 'trial', 'suspended']) },
    ],
    rowActions: ['view', 'edit', 'resend-credentials', 'suspend', 'activate'],
    customActions: {
      suspend: { label: 'Suspendre', icon: 'lock', danger: true, confirm: 'Les utilisateurs de ce tenant ne pourront plus se connecter.' },
      activate: { label: 'Activer', icon: 'unlock' },
      'resend-credentials': { label: 'Renvoyer les identifiants', icon: 'mail', confirm: 'Un nouveau mot de passe est généré pour l’administrateur et lui est envoyé par e-mail ; l’ancien ne fonctionnera plus.' },
    },
    form: [
      {
        title: 'Organisation',
        description: 'La création prépare la base de données de l’école, ses rôles et son année scolaire.',
        fields: [
          { key: 'name', label: 'Nom', required: true, hint: 'Le code et le sous-domaine sont générés automatiquement à partir du nom.' },
          { key: 'domain', label: 'Domaine personnalisé', placeholder: 'ecole.exemple.ci' },
          { key: 'plan', label: 'Plan', type: 'select', optionsKey: 'plans', required: true },
          { key: 'status', label: 'Statut initial', type: 'select', options: statusOptions(['trial', 'active']), createOnly: true },
          { key: 'expires_at', label: "Date d'expiration", type: 'date' },
        ],
      },
      {
        title: 'Administrateur du tenant',
        fields: [
          { key: 'admin_name', label: 'Nom complet', required: true },
          { key: 'admin_email', label: 'E-mail', type: 'email', required: true },
        ],
      },
    ],
    stats: [
      { key: 'students_count', label: 'Élèves' },
      { key: 'users_count', label: 'Utilisateurs' },
    ],
  },

  'platform/demo-requests': {
    endpoint: 'platform/demo-requests',
    title: 'Demandes de démonstration',
    singular: 'demande',
    searchPlaceholder: 'Nom, établissement, e-mail ou téléphone…',
    emptyText: 'Aucune demande de démonstration pour le moment.',
    titleField: 'school',
    columns: [
      { key: 'school', label: 'Établissement', type: 'person', sub: 'city', sortable: true },
      { key: 'name', label: 'Contact' },
      { key: 'role', label: 'Fonction' },
      { key: 'email', label: 'E-mail' },
      { key: 'phone', label: 'Téléphone' },
      { key: 'students', label: 'Élèves' },
      { key: 'sites', label: 'Sites', type: 'number' },
      { key: 'message', label: 'Message' },
      { key: 'created_at', label: 'Reçue le', type: 'datetime', sortable: true },
    ],
    filters: [],
    rowActions: [],
  },

  'platform/schools': {
    endpoint: 'platform/schools',
    title: 'Écoles',
    singular: 'école',
    emptyText: 'Aucune école (les données sont collectées chaque nuit).',
    canCreate: false,
    titleField: 'name',
    columns: [
      { key: 'name', label: 'Établissement', type: 'person', sub: 'tenant_name', sortable: true },
      { key: 'city', label: 'Ville' },
      { key: 'students_count', label: 'Élèves', type: 'number', align: 'right', sortable: true },
      { key: 'status', label: 'Statut', type: 'status' },
    ],
    filters: [{ key: 'status', label: 'Statut', ...s(['active', 'inactive']) }],
    rowActions: [],
  },

  'platform/users': {
    endpoint: 'platform/users',
    title: 'Utilisateurs',
    singular: 'utilisateur',
    canCreate: false,
    emptyText: 'Aucun utilisateur.',
    titleField: 'full_name',
    columns: [
      { key: 'full_name', label: 'Administrateur', type: 'person', sub: 'email' },
      { key: 'tenant_name', label: 'Tenant' },
      { key: 'role', label: 'Rôle', type: 'option', optionsKey: 'allRoles' },
      { key: 'last_login_at', label: 'Dernière connexion', type: 'datetime' },
      { key: 'status', label: 'Statut', type: 'status' },
    ],
    filters: [{ key: 'status', label: 'Statut', ...s(['active', 'suspended']) }],
    rowActions: [],
  },

  'platform/plans': {
    endpoint: 'platform/plans',
    title: 'Plans',
    singular: 'plan',
    createLabel: 'Nouveau plan',
    emptyText: 'Aucun plan.',
    titleField: 'name',
    columns: [],
    form: [
      {
        title: 'Plan',
        fields: [
          { key: 'name', label: 'Nom', required: true },
          { key: 'status', label: 'Statut', type: 'select', ...s(['active', 'inactive']) },
          { key: 'description', label: 'Description', type: 'textarea', span: 2 },
          { key: 'price', label: 'Prix (FCFA)', type: 'number' },
          { key: 'period', label: 'Période', type: 'select', optionsKey: 'periods', required: true },
        ],
      },
      {
        title: 'Limites',
        fields: [
          { key: 'max_schools', label: "Nombre max. d'établissements", type: 'number' },
          { key: 'max_students', label: "Nombre max. d'élèves", type: 'number' },
          { key: 'max_users', label: "Nombre max. d'utilisateurs", type: 'number' },
        ],
      },
      {
        title: 'Fonctionnalités incluses',
        fields: [
          {
            key: 'features',
            label: 'Modules',
            type: 'checkboxes',
            span: 2,
            optionsKey: 'modules',
            hint: 'Les écoles n’ont accès qu’aux modules cochés. Le socle (élèves, classes, enseignants, annonces) est toujours inclus.',
          },
        ],
      },
    ],
  },

  'platform/subscriptions': {
    endpoint: 'platform/subscriptions',
    title: 'Abonnements',
    singular: 'abonnement',
    canCreate: false,
    emptyText: 'Aucun abonnement.',
    titleField: 'tenant_name',
    columns: [
      { key: 'tenant_name', label: 'Tenant', sortable: true },
      { key: 'plan', label: 'Plan' },
      { key: 'starts_at', label: 'Début', type: 'date' },
      { key: 'expires_at', label: 'Expiration', type: 'date', sortable: true },
      { key: 'amount', label: 'Montant', type: 'money', align: 'right' },
      { key: 'status', label: 'Statut', type: 'status' },
    ],
    filters: [{ key: 'status', label: 'Statut', ...s(['active', 'trial', 'expired', 'cancelled', 'suspended']) }],
    rowActions: [],
  },

  'platform/payments': {
    endpoint: 'platform/payments',
    title: 'Paiements SaaS',
    singular: 'paiement',
    canCreate: false,
    emptyText: 'Aucun paiement.',
    titleField: 'reference',
    columns: [
      { key: 'reference', label: 'Référence' },
      { key: 'customer', label: 'Client', type: 'person', sub: 'tenant_name' },
      { key: 'plan', label: 'Plan' },
      { key: 'amount', label: 'Montant', type: 'money', align: 'right', sortable: true },
      { key: 'method', label: 'Méthode' },
      { key: 'paid_at', label: 'Date', type: 'date', sortable: true },
      { key: 'status', label: 'Statut', type: 'status' },
    ],
    filters: [{ key: 'status', label: 'Statut', ...s(['paid', 'pending', 'failed']) }],
    rowActions: [],
  },

  'platform/usage': {
    endpoint: 'platform/usage',
    title: 'Utilisation',
    singular: 'ligne',
    canCreate: false,
    emptyText: "Pas encore de données d'utilisation (collecte quotidienne).",
    titleField: 'tenant_name',
    columns: [
      { key: 'tenant_name', label: 'Tenant', sortable: true },
      { key: 'students', label: 'Élèves', type: 'number', align: 'right', sortable: true },
      { key: 'users', label: 'Utilisateurs', type: 'number', align: 'right' },
      { key: 'storage_mb', label: 'Stockage (Mo)', type: 'number', align: 'right', sortable: true },
      { key: 'sms_sent', label: 'SMS ce mois', type: 'number', align: 'right' },
      { key: 'last_activity', label: 'Dernière activité', type: 'datetime' },
    ],
    rowActions: [],
  },

  'platform/tickets': {
    endpoint: 'platform/tickets',
    title: 'Tickets de support',
    singular: 'ticket',
    canCreate: false,
    emptyText: 'Aucun ticket.',
    titleField: 'subject',
    columns: [
      { key: 'id', label: 'N°', type: 'number' },
      { key: 'subject', label: 'Sujet', type: 'person', sub: 'tenant_name' },
      { key: 'requester', label: 'Demandeur' },
      { key: 'priority', label: 'Priorité', type: 'option', optionsKey: 'priorities' },
      { key: 'updated_at', label: 'Mise à jour', type: 'datetime', sortable: true },
      { key: 'status', label: 'Statut', type: 'status' },
    ],
    filters: [{ key: 'status', label: 'Statut', ...s(['open', 'in_progress', 'resolved']) }],
    rowActions: ['view'],
  },

  'platform/announcements': {
    endpoint: 'platform/announcements',
    title: 'Annonces plateforme',
    singular: 'annonce',
    createLabel: 'Nouvelle annonce',
    emptyText: 'Aucune annonce.',
    titleField: 'title',
    columns: [
      { key: 'title', label: 'Annonce' },
      { key: 'audience', label: 'Destinataires' },
      { key: 'published_at', label: 'Publiée le', type: 'date' },
      { key: 'status', label: 'Statut', type: 'status' },
    ],
    rowActions: ['edit', 'delete'],
    form: [
      {
        title: 'Annonce',
        fields: [
          { key: 'title', label: 'Titre', required: true, span: 2 },
          { key: 'audience', label: 'Destinataires', type: 'select', options: ['Tous les tenants', 'Administrateurs', 'Tenants en essai'].map((v) => ({ value: v, label: v })) },
          { key: 'status', label: 'Statut', type: 'select', ...s(['draft', 'published']) },
          { key: 'body', label: 'Message', type: 'textarea', span: 2, rows: 6 },
        ],
      },
    ],
  },
};

export function getResource(key) {
  const res = RESOURCES[key];
  if (!res) throw new Error(`Ressource inconnue : ${key}`);
  return { key, canCreate: true, rowActions: ['view', 'edit', 'delete'], filters: [], bulkActions: [], ...res };
}
