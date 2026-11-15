<script setup>
import AppIcon from '@/components/ui/AppIcon.vue';
import PhonePreview from '@/components/landing/PhonePreview.vue';
</script>

<template>
  <section class="mx-auto grid max-w-5xl items-center gap-12 px-5 pt-16 md:grid-cols-[1.2fr_1fr]">
    <div>
      <span class="flex size-12 items-center justify-center rounded-2xl bg-mint text-brand-600"><AppIcon name="smartphone" class="size-6" /></span>
      <h1 class="mt-5 text-4xl font-semibold tracking-tight">L’espace parents est dans l’<span class="text-brand-600">application mobile</span></h1>
      <p class="mt-5 text-lg text-muted">Les notes, les absences, les devoirs et les paiements de vos enfants se consultent dans l’application Ardoise sur votre téléphone. Connectez-vous avec le code personnel remis par l’établissement.</p>
      <p class="mt-3 text-muted">Vous n’avez pas encore de code ? Demandez-le au secrétariat de l’école de votre enfant.</p>
      <div class="mt-8 flex flex-wrap gap-3">
        <RouterLink to="/" class="btn btn-lg btn-primary rounded-full">Revenir à l’accueil</RouterLink>
        <RouterLink to="/contact" class="btn btn-lg btn-secondary rounded-full">Nous contacter</RouterLink>
      </div>
    </div>
    <PhonePreview />
  </section>
</template>
