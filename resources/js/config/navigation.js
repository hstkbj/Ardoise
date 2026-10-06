/**
 * Menus par espace. Une entrée est masquée si l'utilisateur n'a pas la `permission`,
 * aucun des `roles`, ou si le module `feature` n'est pas inclus dans le plan de l'école.
 */

export const ADMIN_NAV = [
  {
    label: 'Pilotage',
    items: [
      { label: 'Tableau de bord', to: '/admin/dashboard', icon: 'dashboard' },
      { label: 'Établissements', to: '/admin/schools', icon: 'building', permission: 'schools.view' },
      { label: 'Années scolaires', to: '/admin/academic-years', icon: 'calendar', permission: 'academic_years.view' },
    ],
  },
  {
    label: 'Scolarité',
    items: [
      { label: 'Classes', to: '/admin/classes', icon: 'layers', permission: 'classes.view' },
      { label: 'Élèves', to: '/admin/students', icon: 'users', permission: 'students.view' },
      { label: 'Parents', to: '/admin/parents', icon: 'home', permission: 'parents.view' },
      { label: 'Enseignants', to: '/admin/teachers', icon: 'user', permission: 'teachers.view' },
      { label: 'Matières', to: '/admin/subjects', icon: 'book', permission: 'subjects.view' },
    ],
  },
  {
    label: 'Pédagogie',
    items: [
      { label: 'Évaluations', to: '/admin/assessments', icon: 'clipboard', permission: 'assessments.view', feature: 'grades' },
      { label: 'Saisie des notes', to: '/admin/grades', icon: 'pencil', permission: 'grades.view', feature: 'grades' },
      { label: 'Bulletins', to: '/admin/report-cards', icon: 'file', permission: 'report_cards.view', feature: 'grades' },
      { label: 'Absences', to: '/admin/attendance', icon: 'check-circle', permission: 'attendance.view', feature: 'attendance' },
      { label: 'Emploi du temps', to: '/admin/timetable', icon: 'clock', permission: 'timetable.view', feature: 'timetable' },
      { label: 'Devoirs', to: '/admin/homework', icon: 'list', permission: 'homework.view', feature: 'homework' },
    ],
  },
  {
    label: 'Communication',
    items: [
      { label: 'Annonces', to: '/admin/announcements', icon: 'megaphone', permission: 'announcements.view' },
      { label: 'Notifications', to: '/admin/notifications', icon: 'bell' },
    ],
  },
  {
    label: 'Administration',
    items: [
      { label: 'Frais scolaires', to: '/admin/fees', icon: 'tag', permission: 'fees.view', feature: 'finance' },
      { label: 'Paiements', to: '/admin/payments', icon: 'wallet', permission: 'payments.view', feature: 'finance' },
      { label: 'Documents', to: '/admin/documents', icon: 'folder', permission: 'documents.view', feature: 'documents' },
      { label: 'Utilisateurs', to: '/admin/users', icon: 'shield', permission: 'users.view' },
      { label: 'Rôles et permissions', to: '/admin/roles', icon: 'key', permission: 'roles.view' },
      { label: 'Abonnement', to: '/admin/billing', icon: 'refresh', roles: ['school_admin', 'director'] },
      { label: 'Support', to: '/admin/support', icon: 'help' },
      { label: 'Paramètres', to: '/admin/settings', icon: 'sliders', permission: 'settings.view' },
    ],
  },
];

export const TEACHER_NAV = [
  {
    label: 'Mon espace',
    items: [
      { label: 'Tableau de bord', to: '/teacher/dashboard', icon: 'dashboard' },
      { label: 'Mes classes', to: '/teacher/classes', icon: 'layers' },
      { label: 'Mes élèves', to: '/teacher/students', icon: 'users' },
      { label: 'Emploi du temps', to: '/teacher/timetable', icon: 'clock', feature: 'timetable' },
    ],
  },
  {
    label: 'Pédagogie',
    items: [
      { label: 'Évaluations', to: '/teacher/assessments', icon: 'clipboard', feature: 'grades' },
      { label: 'Saisie des notes', to: '/teacher/grades', icon: 'pencil', feature: 'grades' },
      { label: 'Appel / absences', to: '/teacher/attendance', icon: 'check-circle', feature: 'attendance' },
      { label: 'Devoirs', to: '/teacher/homework', icon: 'list', feature: 'homework' },
    ],
  },
  {
    label: 'Compte',
    items: [
      { label: 'Notifications', to: '/teacher/notifications', icon: 'bell' },
      { label: 'Mon profil', to: '/profile', icon: 'user' },
    ],
  },
];

export const PARENT_NAV = [
  { label: 'Accueil', to: '/parent/dashboard', icon: 'home', mobile: true },
  { label: 'Enfants', to: '/parent/children', icon: 'users', mobile: true },
  { label: 'Notes', to: '/parent/grades', icon: 'file', mobile: true, feature: 'grades' },
  { label: 'Bulletins', to: '/parent/report-cards', icon: 'clipboard', feature: 'grades' },
  { label: 'Absences', to: '/parent/attendance', icon: 'check-circle', feature: 'attendance' },
  { label: 'Devoirs', to: '/parent/homework', icon: 'list', mobile: true, feature: 'homework' },
  { label: 'Emploi du temps', to: '/parent/timetable', icon: 'clock', feature: 'timetable' },
  { label: 'Paiements', to: '/parent/payments', icon: 'wallet', feature: 'finance' },
  { label: 'Annonces', to: '/parent/announcements', icon: 'megaphone' },
  { label: 'Documents', to: '/parent/documents', icon: 'folder', feature: 'documents' },
  { label: 'Alertes', to: '/parent/notifications', icon: 'bell', mobile: true },
  { label: 'Profil', to: '/profile', icon: 'user', mobile: true },
];

export const SUPERADMIN_NAV = [
  {
    label: 'Plateforme',
    items: [
      { label: 'Tableau de bord', to: '/superadmin/dashboard', icon: 'dashboard' },
      { label: 'Statistiques', to: '/superadmin/analytics', icon: 'chart' },
      { label: 'Utilisation', to: '/superadmin/usage', icon: 'activity' },
    ],
  },
  {
    label: 'Clients',
    items: [
      { label: 'Tenants', to: '/superadmin/tenants', icon: 'building' },
      { label: 'Écoles', to: '/superadmin/schools', icon: 'layers' },
      { label: 'Utilisateurs', to: '/superadmin/users', icon: 'users' },
    ],
  },
  {
    label: 'Facturation',
    items: [
      { label: 'Plans', to: '/superadmin/plans', icon: 'tag' },
      { label: 'Abonnements', to: '/superadmin/subscriptions', icon: 'refresh' },
      { label: 'Paiements', to: '/superadmin/payments', icon: 'wallet' },
    ],
  },
  {
    label: 'Relation client',
    items: [
      { label: 'Support', to: '/superadmin/tickets', icon: 'help' },
      { label: 'Annonces', to: '/superadmin/announcements', icon: 'megaphone' },
      { label: 'Paramètres', to: '/superadmin/settings', icon: 'sliders' },
    ],
  },
];
