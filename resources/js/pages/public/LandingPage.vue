<script setup>
import AppIcon from '@/components/ui/AppIcon.vue';
import DashboardPreview from '@/components/landing/DashboardPreview.vue';
import PhonePreview from '@/components/landing/PhonePreview.vue';
import PricingPlans from '@/components/landing/PricingPlans.vue';
import CtaBand from '@/components/landing/CtaBand.vue';

const features = [
  { icon: 'pencil', title: 'Notes et bulletins', text: 'Saisie rapide au clavier, moyennes pondérées, rangs et bulletins PDF générés en un clic.', tint: 'bg-mint' },
  { icon: 'check-circle', title: 'Absences', text: 'L’appel se fait en quelques secondes ; les parents sont prévenus immédiatement.', tint: 'bg-sky' },
  { icon: 'wallet', title: 'Frais et paiements', text: 'Échéanciers automatiques, reçus, relances et paiement mobile money.', tint: 'bg-sun-soft' },
  { icon: 'smartphone', title: 'Application parents', text: 'Un code d’accès remis par l’école suffit : pas de mot de passe à retenir.', tint: 'bg-lavender' },
  { icon: 'clock', title: 'Emplois du temps', text: 'Grille par classe et par enseignant, sans chevauchement possible.', tint: 'bg-blush' },
  { icon: 'shield', title: 'Rôles et permissions', text: 'Chacun ne voit que ce qui le concerne, de la direction au secrétariat.', tint: 'bg-mist' },
];

const steps = [
  { n: '1', title: 'Nous créons votre espace', text: 'Votre école a sa propre base de données, isolée des autres.' },
  { n: '2', title: 'Vous importez vos classes', text: 'Élèves, enseignants et frais : tout est prêt pour la rentrée.' },
  { n: '3', title: 'Les familles se connectent', text: 'Chaque parent reçoit son code et suit ses enfants depuis son téléphone.' },
];
</script>

<template>
  <!-- Hero -->
  <section class="relative overflow-hidden">
    <div class="absolute inset-x-0 top-0 -z-10 h-[620px] bg-gradient-to-b from-cream to-white" aria-hidden="true" />
    <div class="mx-auto max-w-4xl px-5 pt-16 pb-12 text-center sm:pt-24">
      <p class="inline-flex items-center gap-2 rounded-full bg-white px-4 py-1.5 text-[13px] font-medium text-brand-700 shadow-sm ring-1 ring-line">
        <span class="size-2 rounded-full bg-sun" />Nouvelle année scolaire, nouvel outil
      </p>
      <h1 class="mt-6 text-4xl leading-[1.08] font-semibold tracking-tight sm:text-6xl">
        Gérez votre école <span class="relative whitespace-nowrap text-brand-600"><span class="absolute inset-x-0 bottom-1 -z-10 h-4 rounded-full bg-sun/40 sm:h-5" aria-hidden="true" />sereinement</span>, de la rentrée au bulletin
      </h1>
      <p class="mx-auto mt-6 max-w-2xl text-lg text-muted">Ardoise réunit la direction, les enseignants et les familles autour des notes, des absences et des paiements — sur ordinateur comme sur téléphone.</p>
      <div class="mt-9 flex flex-wrap justify-center gap-3">
        <RouterLink to="/contact" class="btn btn-lg btn-primary rounded-full">Demander une démo <AppIcon name="arrow-right" class="size-4" /></RouterLink>
        <RouterLink to="/features" class="btn btn-lg btn-secondary rounded-full">Découvrir les fonctionnalités</RouterLink>
      </div>
    </div>
    <div class="mx-auto max-w-5xl px-5"><DashboardPreview /></div>
  </section>

  <!-- Chiffres -->
  <section class="mx-auto mt-20 grid max-w-5xl grid-cols-2 gap-4 px-5 md:grid-cols-4">
    <div v-for="s in [['1 semaine', 'pour être opérationnel'], ['3 clics', 'pour un bulletin'], ['0 mot de passe', 'pour les parents'], ['100 %', 'de vos données isolées']]" :key="s[0]" class="rounded-3xl bg-mist px-5 py-6 text-center">
      <p class="text-2xl font-semibold tracking-tight">{{ s[0] }}</p>
      <p class="mt-1 text-sm text-muted">{{ s[1] }}</p>
    </div>
  </section>

  <!-- Fonctionnalités -->
  <section class="mx-auto mt-24 max-w-6xl px-5">
    <div class="mx-auto max-w-2xl text-center">
      <h2 class="text-3xl font-semibold tracking-tight sm:text-4xl">Tout ce dont votre école a besoin, <span class="text-brand-600">rien de superflu</span></h2>
      <p class="mt-4 text-muted">Des outils simples, pensés avec des directeurs, des enseignants et des parents.</p>
    </div>
    <div class="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
      <article v-for="f in features" :key="f.title" class="rounded-[28px] p-7" :class="f.tint">
        <span class="flex size-11 items-center justify-center rounded-2xl bg-white text-brand-600 shadow-sm"><AppIcon :name="f.icon" class="size-5" /></span>
        <h3 class="mt-5 text-lg font-semibold">{{ f.title }}</h3>
        <p class="mt-2 text-sm leading-relaxed text-muted">{{ f.text }}</p>
      </article>
    </div>
  </section>

  <!-- Parents -->
  <section class="mx-auto mt-24 max-w-6xl px-5">
    <div class="grid items-center gap-12 rounded-[36px] bg-cream px-6 py-14 sm:px-14 lg:grid-cols-2">
      <div>
        <p class="text-sm font-semibold text-brand-600">Application parents</p>
        <h2 class="mt-3 text-3xl font-semibold tracking-tight">Un simple code, et les parents suivent tout</h2>
        <p class="mt-4 text-muted">L’établissement remet à chaque parent un code personnel. En le saisissant dans l’application, le parent retrouve automatiquement son école et ses enfants : notes, bulletins, absences, devoirs et paiements.</p>
        <ul class="mt-6 space-y-3 text-sm">
          <li v-for="t in ['Aucun mot de passe à créer ni à oublier', 'Un code régénérable à tout moment par l’école', 'Notifications pour chaque absence ou bulletin publié']" :key="t" class="flex gap-2"><AppIcon name="check-circle" class="size-5 shrink-0 text-brand-600" />{{ t }}</li>
        </ul>
      </div>
      <PhonePreview />
    </div>
  </section>

  <!-- Étapes -->
  <section class="mx-auto mt-24 max-w-5xl px-5">
    <h2 class="text-center text-3xl font-semibold tracking-tight">Démarrer en trois étapes</h2>
    <ol class="mt-10 grid gap-5 md:grid-cols-3">
      <li v-for="s in steps" :key="s.n" class="rounded-[28px] border border-line p-7">
        <span class="flex size-10 items-center justify-center rounded-full bg-sun-soft font-semibold text-warn-700">{{ s.n }}</span>
        <h3 class="mt-4 font-semibold">{{ s.title }}</h3>
        <p class="mt-2 text-sm text-muted">{{ s.text }}</p>
      </li>
    </ol>
  </section>

  <!-- Tarifs -->
  <section class="mx-auto mt-24 max-w-6xl px-5">
    <h2 class="text-center text-3xl font-semibold tracking-tight">Une offre pour chaque école</h2>
    <p class="mt-3 text-center text-muted">Sans engagement de durée. Mise en place et formation incluses.</p>
    <div class="mt-12"><PricingPlans /></div>
  </section>

  <div class="mt-24"><CtaBand /></div>
</template>
