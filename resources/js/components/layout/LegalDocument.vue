<script setup>
/**
 * Mise en page d'un texte juridique : titre, date, sommaire et sections.
 * Les passages entre crochets (« [à compléter] ») sont surlignés : ils restent à renseigner.
 */
import { LEGAL_UPDATED } from '@/config/legal';

defineProps({
  title: { type: String, required: true },
  intro: { type: String, default: '' },
  sections: { type: Array, required: true },
});

/** Découpe un texte en segments « normal » / « à compléter ». */
function parts(text) {
  return String(text).split(/(\[[^\]]+\])/g).filter(Boolean).map((t) => ({ t, todo: /^\[[^\]]+\]$/.test(t) }));
}
</script>

<template>
  <article class="mx-auto max-w-3xl px-5 pt-14">
    <header>
      <h1 class="text-4xl font-semibold tracking-tight">{{ title }}</h1>
      <p v-if="intro" class="mt-4 text-lg text-muted">{{ intro }}</p>
      <p class="mt-3 text-sm text-subtle">Dernière mise à jour : {{ LEGAL_UPDATED }}</p>
    </header>

    <nav class="mt-8 rounded-2xl border border-line bg-white p-5" aria-label="Sommaire">
      <p class="text-sm font-semibold">Sommaire</p>
      <ol class="mt-3 list-decimal space-y-1.5 pl-5 text-sm text-muted marker:text-subtle">
        <li v-for="s in sections" :key="s.id"><a :href="`#${s.id}`" class="hover:text-ink hover:underline">{{ s.title }}</a></li>
      </ol>
    </nav>

    <section v-for="(s, n) in sections" :id="s.id" :key="s.id" class="mt-10 scroll-mt-24">
      <h2 class="text-xl font-semibold tracking-tight">{{ n + 1 }}. {{ s.title }}</h2>
      <p v-for="(p, k) in s.paragraphs || []" :key="`p${k}`" class="mt-3 leading-relaxed text-body">
        <template v-for="(seg, j) in parts(p)" :key="j"><mark v-if="seg.todo" class="rounded bg-warn-50 px-1 text-warn-700">{{ seg.t }}</mark><template v-else>{{ seg.t }}</template></template>
      </p>
      <ul v-if="s.items?.length" class="mt-3 list-disc space-y-2 pl-5 leading-relaxed text-body marker:text-subtle">
        <li v-for="(it, k) in s.items" :key="k">
          <template v-for="(seg, j) in parts(it)" :key="j"><mark v-if="seg.todo" class="rounded bg-warn-50 px-1 text-warn-700">{{ seg.t }}</mark><template v-else>{{ seg.t }}</template></template>
        </li>
      </ul>
    </section>

    <p class="mt-12 text-sm text-subtle">
      <RouterLink to="/privacy" class="underline hover:text-ink">Politique de confidentialité</RouterLink> ·
      <RouterLink to="/terms" class="underline hover:text-ink">Conditions générales</RouterLink> ·
      <RouterLink to="/contact" class="underline hover:text-ink">Nous contacter</RouterLink>
    </p>
  </article>
</template>
