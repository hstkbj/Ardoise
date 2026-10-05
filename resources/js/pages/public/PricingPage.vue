<script setup>
import PricingPlans from '@/components/landing/PricingPlans.vue';
import CtaBand from '@/components/landing/CtaBand.vue';

const faq = [
  ['La mise en place est-elle payante ?', 'Non : la création de votre espace, l’import des données et la formation sont inclus.'],
  ['Puis-je essayer avant de m’engager ?', 'Oui, chaque école dispose d’une période d’essai de 30 jours.'],
  ['Comment les parents se connectent-ils ?', 'Avec le code personnel remis par l’établissement, sans mot de passe.'],
  ['Nos données sont-elles séparées des autres écoles ?', 'Oui : chaque école dispose de sa propre base de données.'],
];
</script>

<template>
  <section class="bg-gradient-to-b from-cream to-white">
    <div class="mx-auto max-w-3xl px-5 pt-16 pb-10 text-center sm:pt-20">
      <h1 class="text-4xl font-semibold tracking-tight sm:text-5xl">Des tarifs <span class="text-brand-600">simples</span></h1>
      <p class="mt-5 text-lg text-muted">Choisissez l’offre adaptée à la taille de votre établissement.</p>
    </div>
  </section>
  <section class="mx-auto max-w-6xl px-5"><PricingPlans /></section>
  <section class="mx-auto mt-20 max-w-3xl px-5">
    <h2 class="text-2xl font-semibold tracking-tight">Questions fréquentes</h2>
    <div class="mt-6 divide-y divide-line rounded-[24px] border border-line">
      <details v-for="q in faq" :key="q[0]" class="group px-6 py-5">
        <summary class="cursor-pointer list-none font-medium marker:hidden">{{ q[0] }}</summary>
        <p class="mt-2 text-sm text-muted">{{ q[1] }}</p>
      </details>
    </div>
  </section>
  <div class="mt-24"><CtaBand /></div>
</template>
