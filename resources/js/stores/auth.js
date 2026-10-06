import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import { csrf, onPaymentRequired, onUnauthorized } from '@/services/http';
import { authApi } from '@/services/api';
import { useOptionsStore } from './options';
import { resetParentChildren } from '@/composables/useParentChildren';

/**
 * Session utilisateur (Laravel Sanctum, cookies).
 *  - personnel / enseignants : e-mail ou téléphone + mot de passe (+ code établissement hors sous-domaine)
 *  - parents : code d'accès uniquement
 *  - superadmin : console plateforme, domaine central
 */

export const ROLE_HOME = {
  school_admin: '/admin/dashboard',
  director: '/admin/dashboard',
  academic_manager: '/admin/dashboard',
  accountant: '/admin/payments',
  secretary: '/admin/students',
  teacher: '/teacher/dashboard',
  parent: '/parent/dashboard',
  superadmin: '/superadmin/dashboard',
};

export const ROLE_LABELS = {
  school_admin: 'Administrateur',
  director: 'Directeur',
  academic_manager: 'Responsable pédagogique',
  accountant: 'Comptable',
  secretary: 'Secrétariat',
  teacher: 'Enseignant',
  parent: 'Parent',
  student: 'Élève',
  superadmin: 'Superadmin',
};

export const STAFF_ROLES = ['school_admin', 'director', 'academic_manager', 'accountant', 'secretary'];

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null);
  const loading = ref(false);

  const isAuthenticated = computed(() => !!user.value);
  const role = computed(() => user.value?.role ?? null);
  const roles = computed(() => user.value?.roles ?? []);
  const homePath = computed(() => ROLE_HOME[role.value] ?? '/login');
  const isPlatform = computed(() => role.value === 'superadmin');

  function hasRole(...keys) {
    return keys.some((k) => roles.value.includes(k));
  }

  /** Module inclus dans le plan de l'école (pas de restriction pour la plateforme). */
  function hasFeature(feature) {
    if (!feature || isPlatform.value) return true;
    return (user.value?.features || []).includes(feature);
  }

  const subscription = computed(() => user.value?.subscription ?? null);
  const requiresPayment = computed(() => !!subscription.value?.requires_payment);

  function can(permission) {
    if (!permission) return true;
    const perms = user.value?.permissions || [];
    return perms.includes('*') || perms.includes(permission);
  }

  /** Au démarrage : reprend la session si elle existe (école, puis plateforme). */
  async function init() {
    try {
      user.value = await authApi.me();
    } catch {
      try {
        user.value = await authApi.platformMe();
      } catch {
        user.value = null;
      }
    }
    await afterLogin();
  }

  async function afterLogin() {
    if (user.value && !isPlatform.value && !requiresPayment.value && hasRole(...STAFF_ROLES, 'teacher')) {
      await useOptionsStore().load().catch(() => {});
    }
  }

  async function login({ login: identifier, password, schoolCode, remember, role: profile }) {
    loading.value = true;
    try {
      await csrf();
      user.value =
        profile === 'superadmin'
          ? await authApi.platformLogin({ login: identifier, password, remember })
          : await authApi.login({ login: identifier, password, remember, school_code: schoolCode || undefined });
      await afterLogin();
      return user.value;
    } finally {
      loading.value = false;
    }
  }

  async function loginWithCode(code) {
    loading.value = true;
    try {
      await csrf();
      user.value = await authApi.parentLogin(code);
      return user.value;
    } finally {
      loading.value = false;
    }
  }

  async function logout() {
    try {
      await (isPlatform.value ? authApi.platformLogout() : authApi.logout());
    } catch {
      /* session déjà expirée */
    }
    user.value = null;
    useOptionsStore().reset();
    resetParentChildren();
  }

  function setUser(data) {
    user.value = { ...user.value, ...data };
  }

  // Abonnement expiré en cours de session : l'administrateur va payer, les autres sont déconnectés
  onPaymentRequired(async () => {
    if (hasRole('school_admin')) {
      await refresh();
      if (!window.location.pathname.startsWith('/admin/billing')) window.location.assign('/admin/billing');
      return;
    }
    user.value = null;
    window.location.assign('/login?expired=1');
  });

  onUnauthorized(() => {
    if (user.value) {
      user.value = null;
      if (!window.location.pathname.startsWith('/login')) window.location.assign(`/login?redirect=${encodeURIComponent(window.location.pathname)}`);
    }
  });

  async function refresh() {
    try {
      user.value = isPlatform.value ? await authApi.platformMe() : await authApi.me();
    } catch {
      /* session expirée : géré par l'intercepteur */
    }
  }

  return { user, loading, isAuthenticated, role, roles, homePath, isPlatform, subscription, requiresPayment, hasRole, hasFeature, can, init, refresh, login, loginWithCode, logout, setUser };
});
