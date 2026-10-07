<script setup>
import { ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import AppIcon from '@/components/ui/AppIcon.vue';
import BrandLogo from '@/components/layout/BrandLogo.vue';
import { publicApi } from '@/services/api';
import { useUiStore } from '@/stores/ui';

const route = useRoute();
const ui = useUiStore();
const open = ref(false);
const email = ref('');
const sending = ref(false);

const links = [
  { label: 'Fonctionnalités', to: '/features' },
  { label: 'Tarifs', to: '/pricing' },
  { label: 'Guide', to: '/guide' },
  { label: 'Contact', to: '/contact' },
];

watch(() => route.fullPath, () => (open.value = false));

async function subscribe() {
  if (!/^\S+@\S+\.\S+$/.test(email.value)) return ui.toast('Adresse e-mail invalide.', { tone: 'error' });
  sending.value = true;
  try {
    await publicApi.newsletter(email.value);
    ui.toast('Merci ! Vous êtes inscrit à la lettre d’information.');
    email.value = '';
  } catch (e) {
    ui.error(e);
  } finally {
    sending.value = false;
  }
}
</script>

<template>
  <div class="min-h-screen bg-white">
    <header class="sticky top-0 z-30 border-b border-line-soft bg-white/85 backdrop-blur">
      <div class="mx-auto flex h-16 max-w-6xl items-center justify-between gap-4 px-5">
        <RouterLink to="/" aria-label="Ardoise, accueil"><BrandLogo /></RouterLink>
        <nav class="hidden items-center gap-1 md:flex" aria-label="Site">
          <RouterLink v-for="l in links" :key="l.to" :to="l.to" class="rounded-full px-4 py-2 text-sm font-medium text-body hover:bg-mist" active-class="bg-mist text-ink">{{ l.label }}</RouterLink>
        </nav>
        <div class="hidden items-center gap-2 md:flex">
          <RouterLink to="/login" class="btn btn-ghost rounded-full">Se connecter</RouterLink>
          <RouterLink to="/contact" class="btn btn-primary rounded-full">Demander une démo</RouterLink>
        </div>
        <button type="button" class="btn btn-ghost btn-icon md:hidden" :aria-expanded="open" aria-label="Menu" @click="open = !open"><AppIcon :name="open ? 'x' : 'menu'" class="size-5" /></button>
      </div>
      <nav v-if="open" class="border-t border-line-soft px-5 py-3 md:hidden" aria-label="Site (mobile)">
        <RouterLink v-for="l in links" :key="l.to" :to="l.to" class="block rounded-lg px-3 py-3 font-medium hover:bg-mist">{{ l.label }}</RouterLink>
        <div class="mt-2 grid grid-cols-2 gap-2">
          <RouterLink to="/login" class="btn btn-secondary rounded-full">Se connecter</RouterLink>
          <RouterLink to="/contact" class="btn btn-primary rounded-full">Démo</RouterLink>
        </div>
      </nav>
    </header>

    <main id="main"><RouterView /></main>

    <footer class="mt-24 bg-mist">
      <div class="mx-auto grid max-w-6xl gap-10 px-5 py-14 md:grid-cols-[1.4fr_1fr_1fr_1.4fr]">
        <div>
          <BrandLogo />
          <p class="mt-4 max-w-xs text-sm text-muted">La plateforme de gestion scolaire pensée pour les écoles d’Afrique francophone.</p>
        </div>
        <div>
          <p class="text-sm font-semibold">Produit</p>
          <ul class="mt-3 space-y-2 text-sm text-muted">
            <li><RouterLink to="/features" class="hover:text-ink">Fonctionnalités</RouterLink></li>
            <li><RouterLink to="/pricing" class="hover:text-ink">Tarifs</RouterLink></li>
            <li><RouterLink to="/login" class="hover:text-ink">Connexion</RouterLink></li>
          </ul>
        </div>
        <div>
          <p class="text-sm font-semibold">Entreprise</p>
          <ul class="mt-3 space-y-2 text-sm text-muted">
            <li><RouterLink to="/contact" class="hover:text-ink">Contact</RouterLink></li>
            <li><RouterLink to="/contact" class="hover:text-ink">Demander une démo</RouterLink></li>
          </ul>
        </div>
        <form class="space-y-3" @submit.prevent="subscribe">
          <label for="newsletter" class="text-sm font-semibold">Lettre d’information</label>
          <p class="text-sm text-muted">Nouveautés et conseils, une fois par mois.</p>
          <div class="flex gap-2">
            <input id="newsletter" v-model="email" type="email" class="input rounded-full" placeholder="vous@ecole.ci" autocomplete="email" />
            <button type="submit" class="btn btn-primary rounded-full" :disabled="sending">OK</button>
          </div>
        </form>
      </div>
      <p class="border-t border-line py-6 text-center text-xs text-subtle">© {{ new Date().getFullYear() }} Ardoise. Tous droits réservés.</p>
    </footer>
  </div>
</template>
