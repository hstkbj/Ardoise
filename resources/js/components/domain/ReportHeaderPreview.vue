<script setup>
/**
 * Aperçu en direct de l'en-tête et du pied du bulletin (Paramètres › Bulletins).
 * Reprend la mise en page du PDF (resources/views/pdf/report-card.blade.php) avec des données fictives.
 */
import { computed } from 'vue';

const props = defineProps({
  /** Valeurs du formulaire « Bulletins » (header_lines, header_logo, header_show, header_title…) */
  settings: { type: Object, required: true },
  /** Identité et coordonnées de l'école : name, logo, primary_color, motto, address, phone, email, legal_id */
  school: { type: Object, default: () => ({}) },
  /** URL des images (enregistrées ou choisies à l'instant) : second_logo, signature, stamp */
  images: { type: Object, default: () => ({}) },
});

const color = computed(() => (/^#[0-9A-Fa-f]{6}$/.test(props.school.primary_color || '') ? props.school.primary_color : '#1D5C4D'));
const lines = computed(() => String(props.settings.header_lines || '').split(/\r?\n/).map((l) => l.trim()).filter(Boolean).slice(0, 6));
const logo = computed(() => (props.settings.header_logo === 'none' ? null : props.school.logo || null));
const logoLeft = computed(() => !!logo.value && props.settings.header_logo !== 'center');
const hasSides = computed(() => logoLeft.value || !!props.images.second_logo);
const details = computed(() => {
  const shown = Array.isArray(props.settings.header_show) ? props.settings.header_show : [];
  const s = props.school;
  return [
    ['motto', s.motto],
    ['address', s.address],
    ['phone', s.phone && `Tél. ${s.phone}`],
    ['email', s.email],
    ['legal_id', s.legal_id && `Autorisation n° ${s.legal_id}`],
  ].filter(([key, value]) => shown.includes(key) && value).map(([, value]) => value);
});
const title = computed(() => props.settings.header_title || 'Bulletin de notes');
const showPhoto = computed(() => props.settings.show_student_photo !== 'no');
</script>

<template>
  <figure class="overflow-hidden rounded-xl border border-line bg-white p-4 text-[#15171C] shadow-sm" aria-label="Aperçu de l’en-tête du bulletin">
    <div class="flex items-center gap-2">
      <div v-if="hasSides" class="flex w-14 shrink-0 justify-start">
        <img v-if="logoLeft" :src="logo" alt="" class="max-h-14 max-w-14 object-contain" />
      </div>
      <div class="min-w-0 flex-1 text-center">
        <p v-for="(line, i) in lines" :key="i" class="text-[8px] leading-tight tracking-wide uppercase" :class="i === 0 ? 'font-bold' : 'text-muted'">{{ line }}</p>
        <div v-if="lines.length" class="mx-auto my-1 w-10 border-t" :style="{ borderColor: color }" />
        <img v-if="logo && !logoLeft" :src="logo" alt="" class="mx-auto my-1 max-h-10 object-contain" />
        <p class="text-[13px] font-bold uppercase" :style="{ color }">{{ school.name || 'Nom de l’école' }}</p>
        <p v-for="(d, i) in details" :key="i" class="text-[9px] leading-tight text-muted">{{ d }}</p>
      </div>
      <div v-if="hasSides" class="flex w-14 shrink-0 justify-end">
        <img v-if="images.second_logo" :src="images.second_logo" alt="" class="max-h-14 max-w-14 object-contain" />
      </div>
    </div>

    <div class="mt-2 flex items-center justify-between px-2 py-1 text-white" :style="{ background: color }">
      <span class="text-[11px] font-bold uppercase">{{ title }}</span>
      <span class="text-[8px]">1er trimestre · Année scolaire</span>
    </div>

    <div class="mt-2 flex items-start justify-between gap-2">
      <div class="text-[9px] leading-snug">
        <p class="text-[11px] font-bold">NOM Prénom de l’élève</p>
        <p><span class="text-muted">Matricule</span> EXEMPLE-001 · <span class="text-muted">Classe</span> 5e A</p>
      </div>
      <div v-if="showPhoto" class="flex h-14 w-11 shrink-0 items-center justify-center border border-line bg-ground text-center text-[7px] text-subtle">Photo</div>
    </div>

    <div class="my-2 space-y-1" aria-hidden="true">
      <div v-for="n in 3" :key="n" class="h-2 rounded bg-ground" />
    </div>

    <div class="flex justify-between gap-4 text-[9px]">
      <div class="w-1/2">
        <p class="font-bold">Le professeur principal</p>
        <div class="mt-6 w-4/5 border-t border-line-strong" />
      </div>
      <div class="w-1/2 text-center">
        <p class="font-bold">{{ settings.signature_title || 'Le chef d’établissement' }}</p>
        <div class="flex h-10 items-center justify-center">
          <img v-if="images.signature" :src="images.signature" alt="" class="max-h-8 max-w-24 object-contain" />
          <img v-if="images.stamp" :src="images.stamp" alt="" class="-ml-3 max-h-10 max-w-14 object-contain opacity-90" />
          <div v-if="!images.signature && !images.stamp" class="mt-6 w-4/5 border-t border-line-strong" />
        </div>
        <p v-if="settings.signatory_name">{{ settings.signatory_name }}</p>
      </div>
    </div>
    <figcaption class="mt-3 text-center text-[11px] text-subtle">Aperçu — le PDF reprend la même mise en page.</figcaption>
  </figure>
</template>
