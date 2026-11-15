<script setup>
import BrandLogo from '@/components/layout/BrandLogo.vue';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();

const links = [
  { label: 'Fonctionnalités', to: '/features', text: 'Ce que fait Ardoise pour votre école.' },
  { label: 'Tarifs', to: '/pricing', text: 'Les offres selon la taille de l’établissement.' },
  { label: 'Guide d’utilisation', to: '/guide', text: 'Prendre en main la plateforme pas à pas.' },
  { label: 'Nous contacter', to: '/contact', text: 'Demander une démonstration.' },
];
</script>

<template>
  <div class="flex min-h-screen flex-col bg-cream">
    <header class="mx-auto flex h-16 w-full max-w-6xl items-center px-5">
      <RouterLink to="/" aria-label="Ardoise, accueil"><BrandLogo /></RouterLink>
    </header>
    <main id="main" class="mx-auto flex w-full max-w-2xl flex-1 flex-col items-center justify-center px-5 pb-16 text-center">
      <p class="text-8xl font-semibold tracking-tight text-brand-600" aria-hidden="true">404</p>
      <h1 class="mt-4 text-3xl font-semibold tracking-tight">Page introuvable</h1>
      <p class="mt-3 max-w-md text-muted">Cette page n’existe pas, a été déplacée, ou vous n’y avez pas accès.</p>
      <RouterLink :to="auth.isAuthenticated ? auth.homePath : '/'" class="btn btn-lg btn-primary mt-8 rounded-full">{{ auth.isAuthenticated ? 'Retourner à mon espace' : 'Revenir à l’accueil' }}</RouterLink>

      <template v-if="!auth.isAuthenticated">
        <p class="mt-12 text-sm font-semibold">Ces pages pourraient vous aider</p>
        <ul class="mt-4 grid w-full gap-3 text-left sm:grid-cols-2">
          <li v-for="l in links" :key="l.to">
            <RouterLink :to="l.to" class="block rounded-2xl border border-line bg-white p-4 hover:border-brand-300">
              <span class="font-medium">{{ l.label }}</span>
              <span class="mt-1 block text-sm text-muted">{{ l.text }}</span>
            </RouterLink>
          </li>
        </ul>
      </template>
    </main>
  </div>
</template>
