<script setup>
import BrandLogo from '@/components/layout/BrandLogo.vue';
</script>

<template>
  <div class="grid min-h-screen bg-cream lg:grid-cols-[1fr_minmax(0,560px)]">
    <aside class="relative hidden overflow-hidden bg-brand-600 p-12 text-white lg:flex lg:flex-col lg:justify-between">
      <RouterLink to="/" aria-label="Accueil"><BrandLogo light /></RouterLink>
      <div class="max-w-md">
        <p class="text-3xl leading-tight font-semibold tracking-tight">Toute la vie de l’école, <span class="text-sun">au même endroit.</span></p>
        <p class="mt-4 text-brand-100">Notes, bulletins, absences, paiements et échanges avec les familles — pour la direction, les enseignants et les parents.</p>
      </div>
      <p class="text-sm text-brand-200">© {{ new Date().getFullYear() }} Ardoise</p>
      <div class="absolute -right-24 -bottom-24 size-80 rounded-full bg-white/5" aria-hidden="true" />
      <div class="absolute top-24 -right-10 size-40 rounded-full bg-sun/15" aria-hidden="true" />
    </aside>
    <main id="main" class="flex flex-col px-5 py-8 sm:px-10">
      <RouterLink to="/" class="mb-10 lg:hidden" aria-label="Accueil"><BrandLogo /></RouterLink>
      <div class="m-auto w-full max-w-[420px]"><RouterView /></div>
    </main>
  </div>
</template>
