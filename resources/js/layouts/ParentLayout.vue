<script setup>
/** Espace parent : pensé mobile d'abord (barre d'onglets en bas), menu complet sur grand écran. */
import { useRoute, useRouter } from 'vue-router';
import AppIcon from '@/components/ui/AppIcon.vue';
import BrandLogo from '@/components/layout/BrandLogo.vue';
import { PARENT_NAV } from '@/config/navigation';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
const route = useRoute();
const router = useRouter();
const mobileItems = PARENT_NAV.filter((i) => i.mobile).slice(0, 5);

function isActive(to) {
  return route.path === to || route.path.startsWith(`${to}/`);
}
async function logout() {
  await auth.logout();
  router.push('/login');
}
</script>

<template>
  <div class="min-h-screen bg-cream">
    <header class="sticky top-0 z-20 border-b border-line bg-white/90 backdrop-blur print:hidden">
      <div class="mx-auto flex h-14 max-w-5xl items-center justify-between gap-3 px-4">
        <RouterLink to="/parent/dashboard" aria-label="Accueil"><BrandLogo /></RouterLink>
        <div class="flex items-center gap-1">
          <span class="mr-2 hidden text-[13px] text-muted sm:block">{{ auth.user?.tenant?.name }}</span>
          <RouterLink to="/parent/notifications" class="btn btn-ghost btn-icon" aria-label="Alertes"><AppIcon name="bell" class="size-5" /></RouterLink>
          <button type="button" class="btn btn-ghost btn-icon" aria-label="Se déconnecter" @click="logout"><AppIcon name="logout" class="size-5" /></button>
        </div>
      </div>
      <nav class="mx-auto hidden max-w-5xl gap-1 overflow-x-auto px-4 pb-2 md:flex" aria-label="Espace parent">
        <RouterLink v-for="i in PARENT_NAV" :key="i.to" :to="i.to" class="flex h-8 items-center gap-1.5 rounded-full px-3 text-[13px] font-medium whitespace-nowrap" :class="isActive(i.to) ? 'bg-brand-600 text-white' : 'text-body hover:bg-ground'">
          <AppIcon :name="i.icon" class="size-4" />{{ i.label }}
        </RouterLink>
      </nav>
    </header>

    <main id="main" class="mx-auto max-w-5xl px-4 pt-5 pb-28 md:pb-10"><RouterView :key="$route.path" /></main>

    <nav class="fixed inset-x-0 bottom-0 z-20 border-t border-line bg-white pb-[env(safe-area-inset-bottom)] md:hidden print:hidden" aria-label="Navigation mobile">
      <ul class="grid grid-cols-5">
        <li v-for="i in mobileItems" :key="i.to">
          <RouterLink :to="i.to" class="flex h-16 flex-col items-center justify-center gap-1 text-[11px] font-medium" :class="isActive(i.to) ? 'text-brand-600' : 'text-subtle'">
            <AppIcon :name="i.icon" class="size-5" />{{ i.label }}
          </RouterLink>
        </li>
      </ul>
    </nav>
  </div>
</template>
