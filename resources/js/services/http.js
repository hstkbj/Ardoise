import axios from 'axios';

/**
 * Client HTTP unique vers l'API Laravel (/api/v1).
 *
 * - SPA : session Sanctum (cookies + jeton CSRF) — aucun jeton stocké dans le navigateur.
 * - L'école n'est JAMAIS envoyée par le client : le serveur la déduit du domaine
 *   ou de la session ouverte à la connexion.
 */
const http = axios.create({
  baseURL: import.meta.env.VITE_API_URL || '/api/v1',
  withCredentials: true,
  withXSRFToken: true,
  headers: {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
  timeout: 30000,
});

let unauthorizedHandler = () => {};
let paymentRequiredHandler = () => {};

export function onUnauthorized(fn) {
  unauthorizedHandler = fn;
}

/** 402 : abonnement de l'école expiré (seul l'administrateur peut renouveler). */
export function onPaymentRequired(fn) {
  paymentRequiredHandler = fn;
}

/** À appeler avant une connexion (cookie XSRF-TOKEN). */
export function csrf() {
  return axios.get('/sanctum/csrf-cookie', { withCredentials: true });
}

http.interceptors.response.use(
  (response) => response,
  (error) => {
    const status = error.response?.status;
    if (status === 401 && !error.config?.skipAuthRedirect) unauthorizedHandler();
    if (status === 402 && error.response?.data?.code === 'subscription_expired' && !error.config?.url?.includes('/auth/login')) paymentRequiredHandler();
    // Format Laravel : { message, errors: { champ: ['…'] } }
    const errors = error.response?.data?.errors || {};
    const firstError = Object.values(errors)[0]?.[0];
    const normalized = {
      status,
      message:
        status === 422 && firstError
          ? firstError
          : error.response?.data?.message || (status ? 'Une erreur est survenue. Réessayez.' : 'Connexion au serveur impossible.'),
      errors,
    };
    return Promise.reject(normalized);
  },
);

export default http;
