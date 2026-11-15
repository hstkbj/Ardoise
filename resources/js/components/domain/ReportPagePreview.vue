<script setup>
/**
 * Aperçu en direct du bulletin (éditeur de Paramètres › Bulletins) : en-tête, bloc élève, filigrane,
 * cadre et signature, avec des données fictives. Le tableau des notes n'est qu'une maquette grisée :
 * il n'est pas modifiable. Le PDF reprend la même mise en page (resources/views/pdf/report-card.blade.php).
 */
import { computed } from 'vue';

const props = defineProps({
  /** Valeurs du formulaire « Bulletins » */
  settings: { type: Object, required: true },
  /** Identité et coordonnées de l'école : name, logo, primary_color, motto, address, phone, email, legal_id */
  school: { type: Object, default: () => ({}) },
  /** URL des images (enregistrées ou choisies à l'instant) : second_logo, signature, stamp, watermark_image */
  images: { type: Object, default: () => ({}) },
});

const HEX = /^#[0-9A-Fa-f]{6}$/;
const color = computed(() => (HEX.test(props.settings.accent_color || '') ? props.settings.accent_color : HEX.test(props.school.primary_color || '') ? props.school.primary_color : '#1D5C4D'));
const font = computed(() => (props.settings.font_family === 'serif' ? 'Georgia, "Times New Roman", serif' : 'inherit'));
const lines = computed(() => String(props.settings.header_lines || '').split(/\r?\n/).map((l) => l.trim()).filter(Boolean).slice(0, 6));
const align = computed(() => (props.settings.header_align === 'left' ? 'left' : 'center'));
const logoPx = computed(() => ({ small: 22, medium: 32, large: 44 })[props.settings.logo_size] ?? 32);
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
const titleStyle = computed(() => props.settings.title_style || 'band');

const CELLS = {
  matricule: ['Matricule', 'EXEMPLE-001'],
  class: ['Classe', '5e A (42 élèves)'],
  birth_date: ['Né(e) le', '12/03/2013'],
  term: ['Période', '1er trimestre'],
  year: ['Année scolaire', '2026-2027'],
};
const info = computed(() => ['matricule', 'class', 'birth_date', 'term', 'year'].filter((k) => (Array.isArray(props.settings.student_fields) ? props.settings.student_fields : []).includes(k)).map((k) => CELLS[k]));
const showPhoto = computed(() => props.settings.show_student_photo !== 'no');
const photoLeft = computed(() => props.settings.photo_position === 'left');
const photo = computed(() => ({ small: [24, 30], medium: [30, 38], large: [38, 48] })[props.settings.photo_size] ?? [30, 38]);

const watermarkOpacity = computed(() => Math.max(3, Math.min(30, Number(props.settings.watermark_opacity) || 8)) / 100);
const watermarkWidth = computed(() => (props.settings.watermark_size === 'medium' ? 50 : 70));
const watermark = computed(() => {
  const mode = props.settings.watermark_mode;
  if (mode === 'logo' && props.school.logo) return { type: 'image', src: props.school.logo };
  if (mode === 'image' && props.images.watermark_image) return { type: 'image', src: props.images.watermark_image };
  if (mode === 'text' && String(props.settings.watermark_text || '').trim()) return { type: 'text', text: String(props.settings.watermark_text).slice(0, 40) };
  return null;
});
const border = computed(() => props.settings.page_border);
</script>

<template>
  <figure class="mx-auto w-full max-w-[340px]" aria-label="Aperçu du bulletin">
    <div class="relative aspect-[1/1.414] overflow-hidden rounded-md border border-line bg-white text-[#15171C] shadow-sm" :style="{ fontFamily: font }">
      <div v-if="border === 'thin' || border === 'double'" class="pointer-events-none absolute inset-2 z-20" :style="{ border: `${border === 'double' ? 3 : 1}px ${border === 'double' ? 'double' : 'solid'} ${color}` }" aria-hidden="true" />

      <template v-if="watermark">
        <img v-if="watermark.type === 'image'" :src="watermark.src" alt="" class="pointer-events-none absolute top-[28%] z-0 object-contain" :style="{ width: `${watermarkWidth}%`, left: `${(100 - watermarkWidth) / 2}%`, opacity: watermarkOpacity }" />
        <p v-else class="pointer-events-none absolute inset-x-0 top-[42%] z-0 text-center text-[30px] leading-none font-bold uppercase" :style="{ color, opacity: watermarkOpacity }">{{ watermark.text }}</p>
      </template>

      <div class="relative z-10 p-5">
        <div class="flex items-center gap-2">
          <div v-if="hasSides" class="flex shrink-0 justify-start" :style="{ width: `${logoPx + 4}px` }">
            <img v-if="logoLeft" :src="logo" alt="" class="object-contain" :style="{ maxHeight: `${logoPx}px`, maxWidth: `${logoPx}px` }" />
          </div>
          <div class="min-w-0 flex-1" :style="{ textAlign: align }">
            <p v-for="(line, i) in lines" :key="i" class="text-[6px] leading-tight tracking-wide uppercase" :class="i === 0 ? 'font-bold' : 'text-muted'">{{ line }}</p>
            <div v-if="lines.length" class="my-0.5 w-8 border-t" :class="align === 'center' ? 'mx-auto' : ''" :style="{ borderColor: color }" />
            <img v-if="logo && !logoLeft" :src="logo" alt="" class="my-0.5 object-contain" :class="align === 'center' ? 'mx-auto' : ''" :style="{ maxHeight: `${Math.round(logoPx * 0.75)}px` }" />
            <p class="text-[10px] leading-tight font-bold uppercase" :style="{ color }">{{ school.name || 'Nom de l’école' }}</p>
            <p v-for="(d, i) in details" :key="i" class="text-[6.5px] leading-tight text-muted">{{ d }}</p>
          </div>
          <div v-if="hasSides" class="flex shrink-0 justify-end" :style="{ width: `${logoPx + 4}px` }">
            <img v-if="images.second_logo" :src="images.second_logo" alt="" class="object-contain" :style="{ maxHeight: `${logoPx}px`, maxWidth: `${logoPx}px` }" />
          </div>
        </div>
        <div v-if="settings.header_divider === 'yes'" class="mt-1.5 border-t-2" :style="{ borderColor: color }" />

        <div
          class="mt-1.5 flex items-center justify-between px-1.5 py-1"
          :class="titleStyle === 'band' ? 'text-white' : titleStyle === 'plain' ? 'border-y border-line' : 'border-b-2'"
          :style="titleStyle === 'band' ? { background: color } : titleStyle === 'line' ? { color, borderColor: color } : {}"
        >
          <span class="text-[8px] font-bold uppercase">{{ title }}</span>
          <span class="text-[6px]">1er trimestre · 2026-2027</span>
        </div>

        <div class="mt-1.5 flex items-start gap-2 p-1.5" :class="settings.student_layout === 'card' ? 'border border-line bg-[#F8F8F6]' : ''">
          <div class="min-w-0 flex-1 text-[6.5px] leading-snug">
            <p class="text-[9px] font-bold">NOM Prénom de l’élève</p>
            <div class="mt-0.5 grid grid-cols-2 gap-x-3">
              <p v-for="([label, value], i) in info" :key="i"><span class="text-muted">{{ label }}</span> {{ value }}</p>
            </div>
          </div>
          <div v-if="showPhoto" class="flex shrink-0 items-center justify-center border border-line bg-ground text-center text-[5px] text-subtle" :class="photoLeft ? 'order-first' : 'order-last'" :style="{ width: `${photo[0]}px`, height: `${photo[1]}px` }">Photo</div>
        </div>

        <div class="mt-2 space-y-0.5 opacity-70" aria-hidden="true">
          <div class="h-2 rounded-sm bg-[#E4E4DF]" />
          <div v-for="n in 6" :key="n" class="h-1.5 rounded-sm bg-ground" />
          <div class="h-2 rounded-sm bg-[#E4E4DF]" />
        </div>
        <p class="mt-1 text-center text-[5.5px] text-subtle">Tableau des notes (non modifiable)</p>

        <div class="mt-3 flex justify-between gap-4 text-[6px]">
          <div class="w-1/2"><p class="font-bold">Le professeur principal</p><div class="mt-4 w-4/5 border-t border-line-strong" /></div>
          <div class="w-1/2 text-center">
            <p class="font-bold">{{ settings.signature_title || 'Le chef d’établissement' }}</p>
            <div class="flex h-7 items-center justify-center">
              <img v-if="images.signature" :src="images.signature" alt="" class="max-h-6 max-w-16 object-contain" />
              <img v-if="images.stamp" :src="images.stamp" alt="" class="-ml-2 max-h-7 max-w-10 object-contain opacity-90" />
              <div v-if="!images.signature && !images.stamp" class="mt-3 w-4/5 border-t border-line-strong" />
            </div>
            <p v-if="settings.signatory_name">{{ settings.signatory_name }}</p>
          </div>
        </div>
      </div>
    </div>
    <figcaption class="mt-2 text-center text-[11px] text-subtle">Aperçu : le PDF reprend cette mise en page.</figcaption>
  </figure>
</template>
