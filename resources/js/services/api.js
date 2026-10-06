/**
 * Appels API « métier » (hors CRUD standard de createResource).
 * Chaque fonction indique la route Laravel correspondante (routes/api.php).
 */
import http from './http';
import { send } from './resource';

const data = (r) => r.data.data ?? r.data;

export const authApi = {
  /** POST /auth/login — personnel et enseignants */
  login: (payload) => http.post('/auth/login', payload).then(data),
  /** POST /parent/login — parents, avec leur code d'accès uniquement */
  parentLogin: (code) => http.post('/parent/login', { code }).then(data),
  me: () => http.get('/auth/me', { skipAuthRedirect: true }).then(data),
  logout: () => http.post('/auth/logout'),
  forgotPassword: (payload) => http.post('/auth/forgot-password', payload).then((r) => r.data),
  resetPassword: (payload) => http.post('/auth/reset-password', payload).then((r) => r.data),

  // Superadmin (domaine central)
  platformLogin: (payload) => http.post('/platform/auth/login', payload).then(data),
  platformMe: () => http.get('/platform/auth/me', { skipAuthRedirect: true }).then(data),
  platformLogout: () => http.post('/platform/auth/logout'),
};

export const optionsApi = {
  /** GET /options — listes de référence (classes, matières, enseignants…) */
  all: () => http.get('/options').then(data),
};

export const dashboardApi = {
  school: (params) => http.get('/dashboard/school', { params }).then(data),
  teacher: () => http.get('/teacher/dashboard').then(data),
  teacherClasses: () => http.get('/teacher/classes').then(data),
  platform: () => http.get('/platform/dashboard').then(data),
};

export const gradesApi = {
  /** GET /assessments/{id}/grades → { data: lignes, assessment } */
  sheet: (assessmentId) => http.get(`/assessments/${assessmentId}/grades`).then((r) => r.data),
  /** PUT /assessments/{id}/grades { grades: [{ student_id, score, absent, comment }] } */
  save: (assessmentId, grades) => http.put(`/assessments/${assessmentId}/grades`, { grades }).then((r) => r.data),
  /** POST /assessments/{id}/validate — enregistre puis verrouille */
  validate: (assessmentId, grades) => http.post(`/assessments/${assessmentId}/validate`, { grades }).then(data),
  unlock: (assessmentId, reason) => http.post(`/assessments/${assessmentId}/unlock`, { reason }).then(data),
};

export const attendanceApi = {
  roster: (params) => http.get('/attendance/session', { params }).then(data),
  save: (payload) => http.post('/attendance/session', payload).then((r) => r.data),
  history: (params) => http.get('/attendance', { params }).then((r) => r.data),
  justify: (id, reason) => http.post(`/attendance/${id}/justify`, { reason }).then((r) => r.data),
};

export const timetableApi = {
  get: (params) => http.get('/timetables', { params }).then(data),
  create: (payload) => http.post('/timetables/entries', payload).then(data),
  remove: (id) => http.delete(`/timetables/entries/${id}`),
};

export const reportCardsApi = {
  list: (params) => http.get('/report-cards', { params }).then((r) => r.data),
  get: (studentId, params) => http.get(`/report-cards/${studentId}`, { params }).then(data),
  update: (studentId, payload) => http.put(`/report-cards/${studentId}`, payload).then(data),
  generate: (payload) => http.post('/report-cards/generate', payload).then((r) => r.data),
  publish: (ids, termId) => http.post('/report-cards/publish', { ids, term_id: termId }).then((r) => r.data),
  pdfPath: (studentId, termId) => `/report-cards/${studentId}/pdf${termId ? `?term_id=${termId}` : ''}`,
};

export const paymentsApi = {
  summary: (params) => http.get('/payments/summary', { params }).then(data),
  confirm: (recordId) => http.post(`/payments/records/${recordId}/confirm`).then((r) => r.data),
  cancel: (recordId) => http.post(`/payments/records/${recordId}/cancel`).then((r) => r.data),
  receiptPath: (lineId, recordId) => `/payments/${lineId}/receipt${recordId ? `?record=${recordId}` : ''}`,
};

export const parentsApi = {
  regenerateCode: (id) => http.post(`/parents/${id}/regenerate-code`).then(data),
};

export const classesApi = {
  syncSubjects: (id, subjects) => http.put(`/classes/${id}/subjects`, { subjects }).then(data),
};

export const rolesApi = {
  list: () => http.get('/roles').then(data),
  create: (payload) => http.post('/roles', payload).then(data),
  permissions: () => http.get('/permissions').then((r) => r.data),
  syncPermissions: (id, permissions) => http.put(`/roles/${id}/permissions`, { permissions }).then(data),
};

export const settingsApi = {
  get: (section) => http.get(`/settings/${section}`).then(data),
  save: (section, values) => send('put', `/settings/${section}`, values),
  platformGet: (section) => http.get(`/platform/settings/${section}`).then(data),
  platformSave: (section, values) => http.put(`/platform/settings/${section}`, values).then(data),
};

export const profileApi = {
  update: (payload) => http.put('/profile', payload).then(data),
  password: (payload) => http.put('/profile/password', payload).then((r) => r.data),
};

export const notificationsApi = {
  list: (params) => http.get('/notifications', { params }).then((r) => r.data),
  read: (id) => http.post(`/notifications/${id}/read`),
  readAll: () => http.post('/notifications/read-all'),
};

export const billingApi = {
  /** GET /billing — abonnement de l'école, plans, consommation, historique */
  get: () => http.get('/billing').then(data),
  /** POST /billing/checkout { plan_id, periods } → { reference, url } (page FedaPay) */
  checkout: (payload) => http.post('/billing/checkout', payload).then(data),
  verify: (reference) => http.post('/billing/verify', { reference }).then(data),
};

export const parentApi = {
  children: () => http.get('/parent/children').then(data),
  announcements: () => http.get('/parent/announcements').then(data),
  documents: (studentId) => http.get('/parent/documents', { params: { student_id: studentId } }).then(data),
  requestDocument: (payload) => http.post('/parent/document-requests', payload).then((r) => r.data),
  timetable: (studentId) => http.get('/parent/timetable', { params: { student_id: studentId } }).then(data),
  justify: (attendanceId, payload) => send('post', `/parent/attendance/${attendanceId}/justify`, payload),
  homeworkDone: (homeworkId, studentId, done) => http.post(`/parent/homework/${homeworkId}/done`, { student_id: studentId, done }).then((r) => r.data),
  checkout: (assignmentId, payload) => http.post(`/parent/payments/${assignmentId}/checkout`, payload).then(data),
  verifyPayment: (reference) => http.post('/parent/payments/verify', { reference }).then(data),
};

export const supportApi = {
  /** Côté école : /support-tickets — côté plateforme : /platform/tickets */
  thread: (base, id) => http.get(`/${base}/${id}/messages`).then(data),
  reply: (base, id, body) => http.post(`/${base}/${id}/messages`, { body }).then(data),
  setStatus: (id, status) => http.put(`/platform/tickets/${id}`, { status }).then(data),
};

export const platformApi = {
  tenantAction: (id, action) => http.post(`/platform/tenants/${id}/${action}`).then((r) => r.data),
  resendCredentials: (id) => http.post(`/platform/tenants/${id}/resend-credentials`).then((r) => r.data),
  tenantStats: (id) => http.get(`/platform/tenants/${id}/stats`).then(data),
};

export const publicApi = {
  plans: () => http.get('/public/plans').then(data),
  demoRequest: (payload) => http.post('/public/demo-requests', payload).then((r) => r.data),
  newsletter: (email) => http.post('/newsletter', { email }).then((r) => r.data),
};
