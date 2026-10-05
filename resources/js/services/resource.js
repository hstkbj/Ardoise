import http from './http';

/**
 * Service REST pour une ressource de l'API Laravel.
 *
 *   const students = createResource('students');
 *   students.list({ search, page, per_page, sort, filter: { class_id } })
 *   → { data: [...], meta: { current_page, per_page, total, last_page } }
 *
 * Les fichiers (logo, photo, pièces jointes) sont envoyés en multipart.
 */
export function createResource(endpoint) {
  return {
    list: (params = {}) => http.get(`/${endpoint}`, { params }).then((r) => r.data),
    get: (id) => http.get(`/${endpoint}/${id}`).then((r) => r.data.data ?? r.data),
    create: (payload) => send('post', `/${endpoint}`, payload),
    /** Comme create, mais renvoie la réponse complète { data, meta } (ex. code parent généré). */
    createFull: (payload) => send('post', `/${endpoint}`, payload, { raw: true }),
    update: (id, payload) => send('put', `/${endpoint}/${id}`, payload),
    remove: (id) => http.delete(`/${endpoint}/${id}`).then((r) => r.data),
    action: (id, action, payload = {}) => http.post(`/${endpoint}/${id}/${action}`, payload).then((r) => r.data),
    bulk: (action, ids, payload = {}) => http.post(`/${endpoint}/bulk/${action}`, { ids, ...payload }).then((r) => r.data),
  };
}

function hasFile(payload) {
  return Object.values(payload || {}).some((v) => v instanceof File || (Array.isArray(v) && v.some((x) => x instanceof File)));
}

/** JSON, ou FormData si un fichier est présent (PUT simulé via _method pour PHP). */
export function send(method, url, payload, { raw = false } = {}) {
  const pick = (r) => (raw ? r.data : r.data.data ?? r.data);
  if (!hasFile(payload)) return http[method](url, payload).then(pick);

  const form = new FormData();
  Object.entries(payload).forEach(([key, value]) => {
    if (value === null || value === undefined || value === '') return;
    if (Array.isArray(value)) value.forEach((v) => form.append(`${key}[]`, v));
    else form.append(key, value);
  });
  if (method === 'put') form.append('_method', 'PUT');
  return http.post(url, form).then(pick);
}
