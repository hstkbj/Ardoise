import { createRouter, createWebHistory } from 'vue-router';
import { resourceRoutes } from './helpers';
import { useAuthStore, STAFF_ROLES } from '@/stores/auth';

// Mises en page
import PublicLayout from '@/layouts/PublicLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
const SchoolAdminLayout = () => import('@/layouts/SchoolAdminLayout.vue');
const TeacherLayout = () => import('@/layouts/TeacherLayout.vue');
const ParentLayout = () => import('@/layouts/ParentLayout.vue');
const SuperAdminLayout = () => import('@/layouts/SuperAdminLayout.vue');
const RoleLayout = () => import('@/layouts/RoleLayout.vue');

// Pages
const p = {
  landing: () => import('@/pages/public/LandingPage.vue'),
  features: () => import('@/pages/public/FeaturesPage.vue'),
  pricing: () => import('@/pages/public/PricingPage.vue'),
  contact: () => import('@/pages/public/ContactPage.vue'),
  guide: () => import('@/pages/public/GuidePage.vue'),
  login: () => import('@/pages/public/LoginPage.vue'),
  forgot: () => import('@/pages/public/ForgotPasswordPage.vue'),
  reset: () => import('@/pages/public/ResetPasswordPage.vue'),
  notFound: () => import('@/pages/shared/NotFoundPage.vue'),
  profile: () => import('@/pages/shared/ProfilePage.vue'),
  notifications: () => import('@/pages/shared/NotificationsPage.vue'),
  ticket: () => import('@/pages/shared/TicketShowPage.vue'),

  adminDashboard: () => import('@/pages/admin/DashboardPage.vue'),
  grades: () => import('@/pages/admin/GradesEntryPage.vue'),
  studentShow: () => import('@/pages/admin/StudentShowPage.vue'),
  parentShow: () => import('@/pages/admin/ParentShowPage.vue'),
  teacherShow: () => import('@/pages/admin/TeacherShowPage.vue'),
  reportCards: () => import('@/pages/admin/ReportCardsPage.vue'),
  reportCardShow: () => import('@/pages/admin/ReportCardShowPage.vue'),
  attendance: () => import('@/pages/admin/AttendancePage.vue'),
  attendanceHistory: () => import('@/pages/admin/AttendanceHistoryPage.vue'),
  timetable: () => import('@/pages/admin/TimetablePage.vue'),
  payments: () => import('@/pages/admin/PaymentsPage.vue'),
  paymentShow: () => import('@/pages/admin/PaymentShowPage.vue'),
  roles: () => import('@/pages/admin/RolesPage.vue'),
  settings: () => import('@/pages/admin/SettingsPage.vue'),
  billing: () => import('@/pages/admin/BillingPage.vue'),

  teacherDashboard: () => import('@/pages/teacher/TeacherDashboardPage.vue'),
  teacherClasses: () => import('@/pages/teacher/TeacherClassesPage.vue'),

  parentDashboard: () => import('@/pages/parent/ParentDashboardPage.vue'),
  children: () => import('@/pages/parent/ChildrenPage.vue'),
  childShow: () => import('@/pages/parent/ChildShowPage.vue'),
  parentGrades: () => import('@/pages/parent/GradesPage.vue'),
  parentReportCards: () => import('@/pages/parent/ReportCardsPage.vue'),
  parentReportCard: () => import('@/pages/parent/ReportCardViewPage.vue'),
  parentAttendance: () => import('@/pages/parent/AttendancePage.vue'),
  parentHomework: () => import('@/pages/parent/HomeworkPage.vue'),
  parentTimetable: () => import('@/pages/parent/TimetablePage.vue'),
  parentPayments: () => import('@/pages/parent/PaymentsPage.vue'),
  parentAnnouncements: () => import('@/pages/parent/AnnouncementsPage.vue'),
  parentDocuments: () => import('@/pages/parent/DocumentsPage.vue'),

  platformDashboard: () => import('@/pages/superadmin/PlatformDashboardPage.vue'),
  analytics: () => import('@/pages/superadmin/AnalyticsPage.vue'),
  plans: () => import('@/pages/superadmin/PlansPage.vue'),
};

const A = '/admin';
const T = '/teacher';
const S = '/superadmin';

const routes = [
  {
    path: '/',
    component: PublicLayout,
    children: [
      { path: '', component: p.landing, meta: { title: 'Gestion scolaire' } },
      { path: 'features', component: p.features, meta: { title: 'Fonctionnalités' } },
      { path: 'pricing', component: p.pricing, meta: { title: 'Tarifs' } },
      { path: 'contact', component: p.contact, meta: { title: 'Contact' } },
      { path: 'guide', component: p.guide, meta: { title: 'Guide d’utilisation', allowWhenExpired: true } },
    ],
  },
  {
    path: '/',
    component: AuthLayout,
    children: [
      { path: 'login', component: p.login, meta: { guestOnly: true, title: 'Connexion' } },
      { path: 'forgot-password', component: p.forgot, meta: { guestOnly: true, title: 'Mot de passe oublié' } },
      { path: 'reset-password', component: p.reset, meta: { title: 'Nouveau mot de passe' } },
    ],
  },

  // ── Administration de l'école ────────────────────────────────────────────
  {
    path: A,
    component: SchoolAdminLayout,
    meta: { requiresAuth: true, space: 'admin' },
    children: [
      { path: '', redirect: `${A}/dashboard` },
      { path: 'dashboard', component: p.adminDashboard, meta: { title: 'Tableau de bord' } },
      ...resourceRoutes('schools', 'schools', { base: `${A}/schools`, permission: 'schools' }),
      ...resourceRoutes('academic-years', 'academic-years', { base: `${A}/academic-years`, permission: 'academic_years', only: ['index', 'create', 'edit'] }),
      ...resourceRoutes('classes', 'classes', { base: `${A}/classes`, permission: 'classes' }),
      ...resourceRoutes('students', 'students', { base: `${A}/students`, permission: 'students', show: p.studentShow }),
      ...resourceRoutes('parents', 'parents', { base: `${A}/parents`, permission: 'parents', show: p.parentShow }),
      ...resourceRoutes('teachers', 'teachers', { base: `${A}/teachers`, permission: 'teachers', show: p.teacherShow }),
      ...resourceRoutes('subjects', 'subjects', { base: `${A}/subjects`, permission: 'subjects', only: ['index', 'create', 'edit'] }),
      ...resourceRoutes('assessments', 'assessments', { base: `${A}/assessments`, feature: 'grades', permission: 'assessments', indexProps: { gradesPath: `${A}/grades` } }),
      { path: 'grades', component: p.grades, props: { assessmentsBase: `${A}/assessments` }, meta: { permission: 'grades.view', feature: 'grades', title: 'Saisie des notes' } },
      { path: 'report-cards', component: p.reportCards, meta: { permission: 'report_cards.view', feature: 'grades', title: 'Bulletins' } },
      { path: 'report-cards/:id(\\d+)', component: p.reportCardShow, meta: { permission: 'report_cards.view', feature: 'grades', title: 'Bulletin' } },
      { path: 'attendance', component: p.attendance, meta: { permission: 'attendance.view', feature: 'attendance', title: 'Appel' } },
      { path: 'attendance/history', component: p.attendanceHistory, meta: { permission: 'attendance.view', feature: 'attendance', title: 'Historique des absences' } },
      { path: 'timetable', component: p.timetable, meta: { permission: 'timetable.view', feature: 'timetable', title: 'Emploi du temps' } },
      ...resourceRoutes('homework', 'homework', { base: `${A}/homework`, feature: 'homework', permission: 'homework' }),
      ...resourceRoutes('announcements', 'announcements', { base: `${A}/announcements`, permission: 'announcements', only: ['index', 'create', 'edit'] }),
      { path: 'notifications', component: p.notifications, meta: { title: 'Notifications' } },
      ...resourceRoutes('fees', 'fees', { base: `${A}/fees`, feature: 'finance', permission: 'fees', only: ['index', 'create', 'edit'] }),
      ...resourceRoutes('payments', 'payments', { base: `${A}/payments`, feature: 'finance', permission: 'payments', index: p.payments, show: p.paymentShow, only: ['index', 'create', 'show'] }),
      ...resourceRoutes('documents', 'documents', { base: `${A}/documents`, feature: 'documents', permission: 'documents', only: ['index', 'create'] }),
      ...resourceRoutes('users', 'users', { base: `${A}/users`, permission: 'users', only: ['index', 'create', 'edit'] }),
      { path: 'roles', component: p.roles, meta: { permission: 'roles.view', title: 'Rôles et permissions' } },
      ...resourceRoutes('support', 'support-tickets', { base: `${A}/support`, only: ['index', 'create'] }),
      { path: 'support/:id(\\d+)', component: p.ticket, props: { base: 'support-tickets', listPath: `${A}/support` }, meta: { title: 'Support' } },
      { path: 'settings', component: p.settings, meta: { permission: 'settings.view', title: 'Paramètres' } },
      { path: 'billing', component: p.billing, meta: { roles: ['school_admin', 'director'], allowWhenExpired: true, title: 'Abonnement' } },
    ],
  },

  // ── Espace enseignant ─────────────────────────────────────────────────────
  {
    path: T,
    component: TeacherLayout,
    meta: { requiresAuth: true, space: 'teacher' },
    children: [
      { path: '', redirect: `${T}/dashboard` },
      { path: 'dashboard', component: p.teacherDashboard, meta: { title: 'Tableau de bord' } },
      { path: 'classes', component: p.teacherClasses, meta: { title: 'Mes classes' } },
      ...resourceRoutes('students', 'students', { base: `${T}/students`, show: p.studentShow, showProps: { base: `${T}/students` }, only: ['index', 'show'] }),
      ...resourceRoutes('assessments', 'assessments', { base: `${T}/assessments`, feature: 'grades', indexProps: { gradesPath: `${T}/grades` } }),
      { path: 'grades', component: p.grades, props: { assessmentsBase: `${T}/assessments` }, meta: { feature: 'grades', title: 'Saisie des notes' } },
      { path: 'attendance', component: p.attendance, props: { historyPath: `${T}/attendance/history` }, meta: { feature: 'attendance', title: 'Appel' } },
      { path: 'attendance/history', component: p.attendanceHistory, props: { backPath: `${T}/attendance` }, meta: { feature: 'attendance', title: 'Historique des absences' } },
      { path: 'timetable', component: p.timetable, props: { teacherView: true }, meta: { feature: 'timetable', title: 'Emploi du temps' } },
      ...resourceRoutes('homework', 'homework', { base: `${T}/homework`, feature: 'homework' }),
      { path: 'notifications', component: p.notifications, meta: { title: 'Notifications' } },
    ],
  },

  // ── Espace parent ─────────────────────────────────────────────────────────
  {
    path: '/parent',
    component: ParentLayout,
    meta: { requiresAuth: true, space: 'parent' },
    children: [
      { path: '', redirect: '/parent/dashboard' },
      { path: 'dashboard', component: p.parentDashboard, meta: { title: 'Accueil' } },
      { path: 'children', component: p.children, meta: { title: 'Mes enfants' } },
      { path: 'children/:id(\\d+)', component: p.childShow, meta: { title: 'Enfant' } },
      { path: 'grades', component: p.parentGrades, meta: { feature: 'grades', title: 'Notes' } },
      { path: 'report-cards', component: p.parentReportCards, meta: { feature: 'grades', title: 'Bulletins' } },
      { path: 'report-cards/:id(\\d+)', component: p.parentReportCard, meta: { feature: 'grades', title: 'Bulletin' } },
      { path: 'attendance', component: p.parentAttendance, meta: { feature: 'attendance', title: 'Absences' } },
      { path: 'homework', component: p.parentHomework, meta: { feature: 'homework', title: 'Devoirs' } },
      { path: 'timetable', component: p.parentTimetable, meta: { feature: 'timetable', title: 'Emploi du temps' } },
      { path: 'payments', component: p.parentPayments, meta: { feature: 'finance', title: 'Paiements' } },
      { path: 'announcements', component: p.parentAnnouncements, meta: { title: 'Annonces' } },
      { path: 'documents', component: p.parentDocuments, meta: { feature: 'documents', title: 'Documents' } },
      { path: 'notifications', component: p.notifications, props: { compact: true }, meta: { title: 'Alertes' } },
    ],
  },

  // ── Console plateforme ────────────────────────────────────────────────────
  {
    path: S,
    component: SuperAdminLayout,
    meta: { requiresAuth: true, space: 'superadmin' },
    children: [
      { path: '', redirect: `${S}/dashboard` },
      { path: 'dashboard', component: p.platformDashboard, meta: { title: 'Plateforme' } },
      { path: 'analytics', component: p.analytics, meta: { title: 'Statistiques' } },
      ...resourceRoutes('usage', 'platform/usage', { base: `${S}/usage`, only: ['index'] }),
      ...resourceRoutes('tenants', 'platform/tenants', { base: `${S}/tenants` }),
      ...resourceRoutes('schools', 'platform/schools', { base: `${S}/schools`, only: ['index'] }),
      ...resourceRoutes('users', 'platform/users', { base: `${S}/users`, only: ['index'] }),
      ...resourceRoutes('plans', 'platform/plans', { base: `${S}/plans`, index: p.plans, only: ['index', 'create', 'edit'] }),
      ...resourceRoutes('subscriptions', 'platform/subscriptions', { base: `${S}/subscriptions`, only: ['index'] }),
      ...resourceRoutes('payments', 'platform/payments', { base: `${S}/payments`, only: ['index'] }),
      ...resourceRoutes('tickets', 'platform/tickets', { base: `${S}/tickets`, only: ['index'] }),
      { path: 'tickets/:id(\\d+)', component: p.ticket, props: { base: 'platform/tickets', listPath: `${S}/tickets` }, meta: { title: 'Ticket' } },
      ...resourceRoutes('announcements', 'platform/announcements', { base: `${S}/announcements`, only: ['index', 'create', 'edit'] }),
      { path: 'settings', component: p.settings, props: { platform: true }, meta: { title: 'Paramètres' } },
    ],
  },

  // ── Commun ────────────────────────────────────────────────────────────────
  {
    path: '/profile',
    component: RoleLayout,
    meta: { requiresAuth: true, allowWhenExpired: true },
    children: [{ path: '', component: p.profile, meta: { title: 'Mon profil' } }],
  },
  { path: '/:pathMatch(.*)*', component: p.notFound, meta: { title: 'Page introuvable' } },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior: (to, from, saved) => saved ?? (to.hash ? { el: to.hash } : { top: 0 }),
});

/** Espace autorisé pour l'utilisateur connecté. */
function allowedIn(auth, space) {
  switch (space) {
    case 'admin': return auth.hasRole(...STAFF_ROLES);
    case 'teacher': return auth.hasRole('teacher');
    case 'parent': return auth.hasRole('parent');
    case 'superadmin': return auth.isPlatform;
    default: return true;
  }
}

router.beforeEach((to) => {
  const auth = useAuthStore();
  const space = to.matched.find((r) => r.meta.space)?.meta.space;

  if (to.meta.guestOnly && auth.isAuthenticated) return auth.homePath;

  if (to.matched.some((r) => r.meta.requiresAuth)) {
    if (!auth.isAuthenticated) {
      return {
        path: '/login',
        query: {
          redirect: to.fullPath,
          ...(space === 'superadmin' ? { profile: 'superadmin' } : {}),
        },
      };
    }
    if (space && !allowedIn(auth, space)) return auth.homePath;
    if (to.path === '/profile' && auth.isPlatform) return auth.homePath;
    const permission = to.meta.permission;
    if (permission && !auth.can(permission) && !(space === 'teacher')) return auth.homePath;
    // Abonnement impayé après le délai de grâce : l'administrateur ne peut que renouveler
    if (auth.requiresPayment && !to.matched.some((r) => r.meta.allowWhenExpired)) return '/admin/billing';
    if (to.meta.roles && !auth.hasRole(...to.meta.roles)) return auth.homePath;
    // Module absent du plan de l'école
    if (to.meta.feature && !auth.hasFeature(to.meta.feature)) return to.path === auth.homePath ? '/admin/dashboard' : auth.homePath;
  }
  return true;
});

router.afterEach((to) => {
  document.title = to.meta.title ? `${to.meta.title} · Ardoise` : 'Ardoise';
});

export default router;
