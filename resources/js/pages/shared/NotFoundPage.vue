<script setup>
import { useAuthStore } from '@/stores/auth';
const auth = useAuthStore();
</script>

<template>
  <div class="flex min-h-[70vh] flex-col items-center justify-center px-5 text-center">
    <p class="text-7xl font-semibold tracking-tight text-brand-600">404</p>
    <h1 class="mt-4 text-2xl font-semibold">Page introuvable</h1>
    <p class="mt-2 max-w-md text-muted">Cette page n’existe pas ou vous n’y avez pas accès.</p>
    <RouterLink :to="auth.isAuthenticated ? auth.homePath : '/'" class="btn btn-primary mt-8">Revenir à l’accueil</RouterLink>
  </div>
</template>
