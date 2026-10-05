<script setup>
import AppIcon from '@/components/ui/AppIcon.vue';
import CtaBand from '@/components/landing/CtaBand.vue';

const groups = [
  { title: 'Scolarité', tint: 'bg-mint', icon: 'users', items: ['Inscriptions, matricules automatiques', 'Transferts et changements de classe', 'Fiches élèves et parents', 'Plusieurs établissements par école'] },
  { title: 'Pédagogie', tint: 'bg-sky', icon: 'pencil', items: ['Évaluations et coefficients', 'Saisie des notes au clavier', 'Validation et verrouillage', 'Bulletins, rangs et appréciations'] },
  { title: 'Vie scolaire', tint: 'bg-blush', icon: 'check-circle', items: ['Appel par séance', 'Retards et justificatifs', 'Emplois du temps', 'Devoirs avec pièces jointes'] },
  { title: 'Finances', tint: 'bg-sun-soft', icon: 'wallet', items: ['Frais par niveau', 'Échéanciers automatiques', 'Reçus PDF', 'Suivi des impayés'] },
  { title: 'Communication', tint: 'bg-lavender', icon: 'megaphone', items: ['Annonces ciblées', 'Notifications in-app, e-mail et SMS', 'Documents partagés', 'Demandes des parents'] },
  { title: 'Sécurité', tint: 'bg-mist', icon: 'shield', items: ['Une base de données par école', 'Rôles et permissions détaillés', 'Journal des actions sensibles', 'Codes d’accès parents régénérables'] },
];
</script>

<template>
  <section class="bg-gradient-to-b from-cream to-white">
    <div class="mx-auto max-w-3xl px-5 pt-16 pb-6 text-center sm:pt-20">
      <h1 class="text-4xl font-semibold tracking-tight sm:text-5xl">Des fonctionnalités <span class="text-brand-600">pensées pour l’école</span></h1>
      <p class="mt-5 text-lg text-muted">De l’inscription au bulletin, chaque étape de l’année scolaire est couverte.</p>
    </div>
  </section>
  <section class="mx-auto mt-10 grid max-w-6xl gap-5 px-5 sm:grid-cols-2 lg:grid-cols-3">
    <article v-for="g in groups" :key="g.title" class="rounded-[28px] p-7" :class="g.tint">
      <span class="flex size-11 items-center justify-center rounded-2xl bg-white text-brand-600 shadow-sm"><AppIcon :name="g.icon" class="size-5" /></span>
      <h2 class="mt-5 text-lg font-semibold">{{ g.title }}</h2>
      <ul class="mt-4 space-y-2 text-sm text-body">
        <li v-for="i in g.items" :key="i" class="flex gap-2"><AppIcon name="check" class="mt-0.5 size-4 shrink-0 text-brand-600" />{{ i }}</li>
      </ul>
    </article>
  </section>
  <div class="mt-24"><CtaBand /></div>
</template>
