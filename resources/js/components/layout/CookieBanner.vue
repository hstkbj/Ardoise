<script setup>
import { useCookieConsent } from '@/composables/useCookieConsent';

const { visible, accept, refuse } = useCookieConsent();
</script>

<template>
  <section v-if="visible" class="fixed inset-x-4 bottom-4 z-50 mx-auto max-w-2xl rounded-2xl border border-line bg-white p-5 shadow-xl print:hidden" role="region" aria-label="Cookies" aria-live="polite">
    <p class="font-semibold">Cookies</p>
    <p class="mt-1 text-sm text-body">
      Ardoise utilise uniquement les cookies indispensables au fonctionnement (session et sécurité). Aucun cookie publicitaire.
      Vous pouvez accepter ou refuser les cookies facultatifs que nous pourrions ajouter, et changer d’avis à tout moment.
      <RouterLink to="/privacy#cookies" class="underline hover:text-ink">En savoir plus</RouterLink>
    </p>
    <div class="mt-4 flex flex-wrap justify-end gap-2">
      <button type="button" class="btn btn-secondary rounded-full" @click="refuse">Refuser les facultatifs</button>
      <button type="button" class="btn btn-primary rounded-full" @click="accept">Accepter</button>
    </div>
  </section>
</template>
