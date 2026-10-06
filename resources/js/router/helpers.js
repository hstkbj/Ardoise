/**
 * Génère les routes CRUD d'une ressource déclarée dans config/resources.js.
 *   resourceRoutes('students', 'students', { show: StudentShowPage, permission: 'students', feature: 'finance' })
 *   → students, students/create, students/:id, students/:id/edit
 */
const ResourceIndex = () => import('@/pages/generic/ResourceIndex.vue');
const ResourceForm = () => import('@/pages/generic/ResourceForm.vue');
const ResourceShow = () => import('@/pages/generic/ResourceShow.vue');

export function resourceRoutes(path, resource, { base, permission, feature, index, show, form, indexProps = {}, showProps = {}, only } = {}) {
  const fullBase = base ?? `/${path}`;
  const props = { resource, base: fullBase };
  const perm = (action) => (permission ? `${permission}.${action}` : undefined);
  const meta = (action) => ({ permission: perm(action), feature });
  const routes = {
    index: { path, component: index ?? ResourceIndex, props: { ...props, ...indexProps }, meta: meta('view') },
    create: { path: `${path}/create`, component: form ?? ResourceForm, props, meta: meta('create') },
    show: { path: `${path}/:id(\\d+)`, component: show ?? ResourceShow, props: { ...props, ...showProps }, meta: meta('view') },
    edit: { path: `${path}/:id(\\d+)/edit`, component: form ?? ResourceForm, props, meta: meta('update') },
  };
  return (only ?? ['index', 'create', 'show', 'edit']).map((k) => routes[k]);
}
