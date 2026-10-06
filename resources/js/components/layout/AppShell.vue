<script setup>
/**
 * Coque des espaces connectés : barre latérale (groupes de liens), barre du haut,
 * menu mobile. Les entrées avec `permission` sont masquées si l'utilisateur ne l'a pas.
 */
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AppIcon from '@/components/ui/AppIcon.vue';
import Avatar from '@/components/ui/Avatar.vue';
import BrandLogo from './BrandLogo.vue';
import { useAuthStore, ROLE_LABELS } from '@/stores/auth';
import { notificationsApi } from '@/services/api';

const props = defineProps({
  nav: { type: Array, required: true },
  space: { type: String, default: '' },
  notificationsTo: { type: String, default: '' },
  dark: { type: Boolean, default: false },
});

const auth = useAuthStore();
const route = useRoute();
const router = useRouter();
const mobileOpen = ref(false);
const menuOpen = ref(false);
const unread = ref(0);

/** Abonnement impayé après le délai de grâce : seule la page Abonnement reste dans le menu. */
function isVisible(item) {
  if (auth.requiresPayment) return item.to === '/admin/billing';
  return auth.can(item.permission) && auth.hasFeature(item.feature) && (!item.roles || auth.hasRole(...item.roles));
}

const groups = computed(() =>
  props.nav
    .map((g) => ({ ...g, items: g.items.filter((i) => isVisible(i)) }))
    .filter((g) => g.items.length),
);

function isActive(to) {
  return route.path === to || route.path.startsWith(`${to}/`);
}

async function loadUnread() {
  if (!props.notificationsTo || auth.isPlatform || auth.requiresPayment) return;
  try {
    const res = await notificationsApi.list({ per_page: 1 });
    unread.value = res.meta?.unread ?? 0;
  } catch {
    /* silencieux */
  }
}

async function logout() {
  await auth.logout();
  router.push('/login');
}

function onDocClick(e) {
  if (!e.target.closest?.('[data-user-menu]')) menuOpen.value = false;
}

watch(() => route.fullPath, () => {
  mobileOpen.value = false;
  menuOpen.value = false;
});

let timer;
onMounted(() => {
  loadUnread();
  timer = setInterval(loadUnread, 60000);
  document.addEventListener('click', onDocClick);
});
onUnmounted(() => {
  clearInterval(timer);
  document.removeEventListener('click', onDocClick);
});
</script>

<template>
  <div class="min-h-screen bg-ground">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-50 focus:rounded focus:bg-white focus:px-3 focus:py-2">Aller au contenu</a>

    <!-- Barre latérale -->
    <div v-if="mobileOpen" class="fixed inset-0 z-30 bg-ink/40 lg:hidden" @click="mobileOpen = false" />
    <aside
      class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col border-r transition-transform lg:translate-x-0 print:hidden"
      :class="[mobileOpen ? 'translate-x-0' : '-translate-x-full', dark ? 'border-brand-800 bg-brand-800 text-white' : 'border-line bg-white']"
    >
      <div class="flex h-16 items-center justify-between px-5">
        <RouterLink :to="auth.homePath" aria-label="Accueil"><BrandLogo :light="dark" /></RouterLink>
        <button type="button" class="btn btn-ghost btn-icon lg:hidden" aria-label="Fermer le menu" @click="mobileOpen = false"><AppIcon name="x" class="size-5" /></button>
      </div>
      <p v-if="space" class="px-5 pb-2 text-xs font-medium tracking-wide uppercase" :class="dark ? 'text-brand-200' : 'text-subtle'">{{ space }}</p>
      <nav class="flex-1 space-y-5 overflow-y-auto px-3 pb-6" aria-label="Navigation principale">
        <div v-for="g in groups" :key="g.label">
          <p class="px-2 pb-1.5 text-[11px] font-semibold tracking-wider uppercase" :class="dark ? 'text-brand-300' : 'text-subtle'">{{ g.label }}</p>
          <ul class="space-y-0.5">
            <li v-for="item in g.items" :key="item.to">
              <RouterLink
                :to="item.to"
                class="flex h-9 items-center gap-3 rounded-lg px-2.5 text-[13.5px] font-medium"
                :class="isActive(item.to)
                  ? (dark ? 'bg-white/12 text-white' : 'bg-brand-100 text-brand-700')
                  : (dark ? 'text-brand-100 hover:bg-white/8' : 'text-body hover:bg-ground')"
                :aria-current="isActive(item.to) ? 'page' : undefined"
              >
                <AppIcon :name="item.icon" class="size-[18px] shrink-0" />
                <span class="truncate">{{ item.label }}</span>
              </RouterLink>
            </li>
          </ul>
        </div>
      </nav>
    </aside>

    <div class="lg:pl-64">
      <header class="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-line bg-white/90 px-4 backdrop-blur sm:px-6 print:hidden">
        <button type="button" class="btn btn-ghost btn-icon lg:hidden" aria-label="Ouvrir le menu" @click="mobileOpen = true"><AppIcon name="menu" class="size-5" /></button>
        <div class="min-w-0 flex-1"><slot name="topbar" /></div>
        <RouterLink v-if="notificationsTo" :to="notificationsTo" class="btn btn-ghost btn-icon relative" :aria-label="`Notifications${unread ? ` (${unread} non lues)` : ''}`">
          <AppIcon name="bell" class="size-5" />
          <span v-if="unread" class="absolute top-1.5 right-1.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-danger-600 px-1 text-[10px] font-semibold text-white">{{ unread > 9 ? '9+' : unread }}</span>
        </RouterLink>
        <div class="relative" data-user-menu>
          <button type="button" class="flex items-center gap-2 rounded-lg px-1.5 py-1 hover:bg-ground" :aria-expanded="menuOpen" aria-haspopup="menu" @click="menuOpen = !menuOpen">
            <Avatar :name="auth.user?.name || ''" size="sm" />
            <span class="hidden text-left sm:block">
              <span class="block max-w-40 truncate text-[13px] font-semibold">{{ auth.user?.name }}</span>
              <span class="block text-[11px] text-subtle">{{ ROLE_LABELS[auth.role] || auth.role }}<template v-if="auth.user?.tenant"> · {{ auth.user.tenant.name }}</template></span>
            </span>
            <AppIcon name="chevron-down" class="size-4 text-subtle" />
          </button>
          <div v-if="menuOpen" role="menu" class="absolute right-0 mt-2 w-56 overflow-hidden rounded-xl border border-line bg-white py-1 shadow-lg">
            <RouterLink v-if="!auth.isPlatform" to="/profile" role="menuitem" class="flex items-center gap-2.5 px-4 py-2.5 text-sm hover:bg-ground"><AppIcon name="user" class="size-4 text-subtle" />Mon profil</RouterLink>
            <button type="button" role="menuitem" class="flex w-full items-center gap-2.5 px-4 py-2.5 text-left text-sm text-danger-600 hover:bg-danger-50" @click="logout"><AppIcon name="logout" class="size-4" />Se déconnecter</button>
          </div>
        </div>
      </header>
      <main id="main" class="mx-auto w-full max-w-[1400px] px-4 py-6 sm:px-6 lg:px-8"><slot /></main>
    </div>
  </div>
</template>
