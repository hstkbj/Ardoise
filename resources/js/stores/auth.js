import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import { csrf, onUnauthorized } from '@/services/http';
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
    if (user.value && !isPlatform.value && hasRole(...STAFF_ROLES, 'teacher')) {
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

  onUnauthorized(() => {
    if (user.value) {
      user.value = null;
      if (!window.location.pathname.startsWith('/login')) window.location.assign(`/login?redirect=${encodeURIComponent(window.location.pathname)}`);
    }
  });

  return { user, loading, isAuthenticated, role, roles, homePath, isPlatform, hasRole, can, init, login, loginWithCode, logout, setUser };
});
