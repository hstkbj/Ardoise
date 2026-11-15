<script setup>
/**
 * Éditeur du bulletin (Paramètres › Bulletins) : modèles prêts à l'emploi, puis réglages par rubrique
 * (style, en-tête, logos et images, élève, filigrane, signature) avec aperçu en direct.
 * Le tableau des notes et le bas du bulletin ne sont pas modifiables ici.
 *
 * Les valeurs sont celles du formulaire de SettingsPage (`values`) : l'éditeur les modifie sur place,
 * le bouton « Enregistrer » de la page les envoie.
 */
import { ref } from 'vue';
import FormField from '@/components/ui/FormField.vue';
import OptionPills from '@/components/ui/OptionPills.vue';
import AppIcon from '@/components/ui/AppIcon.vue';
import ReportPagePreview from '@/components/domain/ReportPagePreview.vue';
import { openFile } from '@/utils/format';

const props = defineProps({
  values: { type: Object, required: true },
  errors: { type: Object, default: () => ({}) },
  school: { type: Object, default: () => ({}) },
  /** URL des images affichées : second_logo, signature, stamp, watermark_image */
  images: { type: Object, default: () => ({}) },
  readOnly: { type: Boolean, default: false },
});
const emit = defineEmits(['remove-image']);

const SECTIONS = [
  { key: 'style', label: 'Style général' },
  { key: 'header', label: 'En-tête' },
  { key: 'logos', label: 'Logos et images' },
  { key: 'student', label: 'Élève' },
  { key: 'watermark', label: 'Filigrane' },
  { key: 'signature', label: 'Signature' },
];
const section = ref('style');

// Modèles : ils règlent la mise en page, jamais les textes (lignes officielles, titre, signataire).
const PRESETS = [
  { key: 'classic', label: 'Classique', text: 'Bandeau coloré, en-tête centré.', set: { accent_color: '', font_family: 'sans', page_border: 'none', header_align: 'center', logo_size: 'medium', header_divider: 'no', title_style: 'band', student_layout: 'plain', photo_position: 'right', photo_size: 'medium', watermark_mode: 'none' } },
  { key: 'modern', label: 'Moderne', text: 'En-tête à gauche, titre souligné, élève encadré.', set: { accent_color: '', font_family: 'sans', page_border: 'none', header_align: 'left', logo_size: 'large', header_divider: 'yes', title_style: 'line', student_layout: 'card', photo_position: 'right', photo_size: 'small', watermark_mode: 'logo', watermark_opacity: 6, watermark_size: 'large' } },
  { key: 'official', label: 'Officiel', text: 'Police à empattements, double cadre, logo en filigrane.', set: { accent_color: '', font_family: 'serif', page_border: 'double', header_align: 'center', logo_size: 'medium', header_divider: 'yes', title_style: 'plain', student_layout: 'plain', photo_position: 'left', photo_size: 'medium', watermark_mode: 'logo', watermark_opacity: 8, watermark_size: 'large' } },
];

function set(key, value) {
  props.values[key] = value;
  props.values.design_preset = 'custom';
}
function applyPreset(preset) {
  Object.assign(props.values, preset.set, { design_preset: preset.key });
}
function toggle(key, value) {
  const list = Array.isArray(props.values[key]) ? [...props.values[key]] : [];
  const i = list.indexOf(value);
  i === -1 ? list.push(value) : list.splice(i, 1);
  set(key, list);
}

const yesNo = [{ value: 'yes', label: 'Oui' }, { value: 'no', label: 'Non' }];
const DETAILS = [{ value: 'motto', label: 'Devise' }, { value: 'address', label: 'Adresse' }, { value: 'phone', label: 'Téléphone' }, { value: 'email', label: 'E-mail' }, { value: 'legal_id', label: 'N° d’autorisation' }];
const STUDENT_FIELDS = [{ value: 'matricule', label: 'Matricule' }, { value: 'class', label: 'Classe et effectif' }, { value: 'birth_date', label: 'Date de naissance' }, { value: 'term', label: 'Période' }, { value: 'year', label: 'Année scolaire' }];

const FILES = {
  second_logo: { key: 'second_logo', label: 'Second logo, à droite (facultatif)', type: 'file', accept: 'image/png,image/jpeg', hint: 'Armoiries, logo du ministère… PNG ou JPG, 2 Mo max.' },
  watermark_image: { key: 'watermark_image', label: 'Image du filigrane', type: 'file', accept: 'image/png,image/jpeg', hint: 'PNG ou JPG, 2 Mo max. Un logo à fond transparent donne le meilleur rendu.' },
  signature: { key: 'signature', label: 'Signature', type: 'file', accept: 'image/png,image/jpeg', hint: 'Image PNG à fond transparent de préférence.' },
  stamp: { key: 'stamp', label: 'Cachet de l’école', type: 'file', accept: 'image/png,image/jpeg', hint: 'Image PNG à fond transparent de préférence.' },
};
</script>

<template>
  <div class="grid gap-6 border-t border-line-soft pt-5 lg:grid-cols-[minmax(0,1fr)_340px]">
    <div class="min-w-0 space-y-5">
      <div>
        <h2 class="text-base font-semibold">Éditeur du bulletin</h2>
        <p class="mt-1 text-sm text-muted">Choisissez un modèle, puis ajustez chaque rubrique. L’aperçu à droite change en direct. Le tableau des notes et le bas du bulletin restent identiques.</p>
      </div>

      <fieldset :disabled="readOnly" class="grid gap-2 sm:grid-cols-3">
        <legend class="sr-only">Modèles</legend>
        <button
          v-for="p in PRESETS"
          :key="p.key"
          type="button"
          class="rounded-xl border p-3 text-left transition-colors disabled:opacity-60"
          :class="values.design_preset === p.key ? 'border-brand-600 bg-brand-50' : 'border-line hover:bg-ground-soft'"
          :aria-pressed="values.design_preset === p.key"
          @click="applyPreset(p)"
        >
          <span class="block text-sm font-semibold">{{ p.label }}</span>
          <span class="mt-0.5 block text-xs text-muted">{{ p.text }}</span>
        </button>
      </fieldset>
      <p v-if="values.design_preset === 'custom'" class="-mt-2 text-xs text-subtle">Mise en page personnalisée.</p>

      <div role="tablist" aria-label="Rubriques du bulletin" class="flex flex-wrap gap-1.5 border-b border-line pb-3">
        <button
          v-for="s in SECTIONS"
          :key="s.key"
          type="button"
          role="tab"
          :aria-selected="section === s.key"
          class="min-h-9 rounded-lg px-3 py-1.5 text-sm"
          :class="section === s.key ? 'bg-ink font-medium text-white' : 'text-body hover:bg-ground-soft'"
          @click="section = s.key"
        >{{ s.label }}</button>
      </div>

      <fieldset :disabled="readOnly" class="space-y-5">
        <!-- Style général -->
        <div v-if="section === 'style'" class="space-y-5">
          <div>
            <p class="label">Couleur principale</p>
            <div class="mt-1 flex flex-wrap items-center gap-2">
              <input type="color" :value="values.accent_color || school.primary_color || '#1D5C4D'" class="size-10 cursor-pointer rounded-lg border border-line bg-white p-1" aria-label="Choisir la couleur" @input="set('accent_color', $event.target.value)" />
              <code class="rounded bg-ground px-2 py-1 text-xs">{{ values.accent_color || school.primary_color || '#1D5C4D' }}</code>
              <button v-if="values.accent_color" type="button" class="btn btn-ghost btn-sm" @click="set('accent_color', '')">Reprendre la couleur de l’école</button>
            </div>
            <p class="mt-1 text-xs text-subtle">Bandeau du titre, nom de l’école, filets et cadre. Par défaut : la couleur de l’onglet Identité.</p>
            <p v-if="errors.accent_color" class="mt-1 text-xs text-danger-600">{{ errors.accent_color }}</p>
          </div>
          <div>
            <p class="label">Police de l’en-tête et de l’élève</p>
            <OptionPills class="mt-1" label="Police" :model-value="values.font_family" :options="[{ value: 'sans', label: 'Sans empattements' }, { value: 'serif', label: 'Avec empattements' }]" @update:model-value="set('font_family', $event)" />
          </div>
          <div>
            <p class="label">Cadre autour de la page</p>
            <OptionPills class="mt-1" label="Cadre" :model-value="values.page_border" :options="[{ value: 'none', label: 'Aucun' }, { value: 'thin', label: 'Filet simple' }, { value: 'double', label: 'Double filet' }]" @update:model-value="set('page_border', $event)" />
          </div>
        </div>

        <!-- En-tête -->
        <div v-if="section === 'header'" class="space-y-5">
          <FormField v-model="values.header_lines" :field="{ key: 'header_lines', label: 'Lignes officielles (une par ligne)', type: 'textarea', rows: 3, placeholder: 'République du Bénin\nMinistère des Enseignements Secondaire…\nDirection départementale…', hint: '6 lignes maximum, imprimées en petites capitales au-dessus du nom de l’école.' }" :error="errors.header_lines" />
          <FormField v-model="values.header_title" :field="{ key: 'header_title', label: 'Titre du document', placeholder: 'Bulletin de notes' }" :error="errors.header_title" />
          <div>
            <p class="label">Alignement du texte</p>
            <OptionPills class="mt-1" label="Alignement" :model-value="values.header_align" :options="[{ value: 'center', label: 'Centré' }, { value: 'left', label: 'À gauche' }]" @update:model-value="set('header_align', $event)" />
          </div>
          <div>
            <p class="label">Informations sous le nom de l’école</p>
            <div class="mt-1 flex flex-wrap gap-2">
              <label v-for="d in DETAILS" :key="d.value" class="flex min-h-9 cursor-pointer items-center gap-2 rounded-lg border border-line px-3 py-1.5 text-sm has-[:checked]:border-brand-600 has-[:checked]:bg-brand-50">
                <input type="checkbox" class="size-4 accent-brand-600" :checked="(values.header_show || []).includes(d.value)" @change="toggle('header_show', d.value)" />{{ d.label }}
              </label>
            </div>
            <p class="mt-1 text-xs text-subtle">Elles viennent des onglets Identité et Coordonnées.</p>
          </div>
          <div>
            <p class="label">Style du titre</p>
            <OptionPills class="mt-1" label="Style du titre" :model-value="values.title_style" :options="[{ value: 'band', label: 'Bandeau coloré' }, { value: 'line', label: 'Titre souligné' }, { value: 'plain', label: 'Simple' }]" @update:model-value="set('title_style', $event)" />
          </div>
          <div>
            <p class="label">Filet sous l’en-tête</p>
            <OptionPills class="mt-1" label="Filet" :model-value="values.header_divider" :options="yesNo" @update:model-value="set('header_divider', $event)" />
          </div>
        </div>

        <!-- Logos et images -->
        <div v-if="section === 'logos'" class="space-y-5">
          <div>
            <p class="label">Logo de l’école</p>
            <OptionPills class="mt-1" label="Position du logo" :model-value="values.header_logo" :options="[{ value: 'left', label: 'À gauche' }, { value: 'center', label: 'Au centre' }, { value: 'none', label: 'Masqué' }]" @update:model-value="set('header_logo', $event)" />
            <p class="mt-1 text-xs text-subtle">Le logo se change dans l’onglet Identité.</p>
          </div>
          <div>
            <p class="label">Taille des logos</p>
            <OptionPills class="mt-1" label="Taille des logos" :model-value="values.logo_size" :options="[{ value: 'small', label: 'Petite' }, { value: 'medium', label: 'Moyenne' }, { value: 'large', label: 'Grande' }]" @update:model-value="set('logo_size', $event)" />
          </div>
          <div class="flex flex-col gap-2">
            <FormField v-model="values.second_logo" :field="FILES.second_logo" :error="errors.second_logo" />
            <div v-if="images.second_logo" class="flex items-center gap-3">
              <img :src="images.second_logo" alt="Second logo" class="size-12 rounded-lg border border-line bg-white object-contain" />
              <button type="button" class="btn btn-ghost btn-sm" @click="emit('remove-image', 'second_logo')"><AppIcon name="trash" class="size-4" />Retirer</button>
            </div>
          </div>
        </div>

        <!-- Élève -->
        <div v-if="section === 'student'" class="space-y-5">
          <div>
            <p class="label">Informations affichées</p>
            <div class="mt-1 flex flex-wrap gap-2">
              <label v-for="f in STUDENT_FIELDS" :key="f.value" class="flex min-h-9 cursor-pointer items-center gap-2 rounded-lg border border-line px-3 py-1.5 text-sm has-[:checked]:border-brand-600 has-[:checked]:bg-brand-50">
                <input type="checkbox" class="size-4 accent-brand-600" :checked="(values.student_fields || []).includes(f.value)" @change="toggle('student_fields', f.value)" />{{ f.label }}
              </label>
            </div>
            <p class="mt-1 text-xs text-subtle">Le nom de l’élève est toujours affiché.</p>
          </div>
          <div>
            <p class="label">Présentation</p>
            <OptionPills class="mt-1" label="Présentation" :model-value="values.student_layout" :options="[{ value: 'plain', label: 'Simple' }, { value: 'card', label: 'Encadrée' }]" @update:model-value="set('student_layout', $event)" />
          </div>
          <div>
            <p class="label">Photo de l’élève</p>
            <OptionPills class="mt-1" label="Photo de l’élève" :model-value="values.show_student_photo" :options="[{ value: 'yes', label: 'Afficher' }, { value: 'no', label: 'Masquer' }]" @update:model-value="set('show_student_photo', $event)" />
            <p class="mt-1 text-xs text-subtle">Elle s’affiche si la fiche de l’élève en a une.</p>
          </div>
          <template v-if="values.show_student_photo !== 'no'">
            <div>
              <p class="label">Position de la photo</p>
              <OptionPills class="mt-1" label="Position de la photo" :model-value="values.photo_position" :options="[{ value: 'right', label: 'À droite' }, { value: 'left', label: 'À gauche' }]" @update:model-value="set('photo_position', $event)" />
            </div>
            <div>
              <p class="label">Taille de la photo</p>
              <OptionPills class="mt-1" label="Taille de la photo" :model-value="values.photo_size" :options="[{ value: 'small', label: 'Petite' }, { value: 'medium', label: 'Moyenne' }, { value: 'large', label: 'Grande' }]" @update:model-value="set('photo_size', $event)" />
            </div>
          </template>
        </div>

        <!-- Filigrane -->
        <div v-if="section === 'watermark'" class="space-y-5">
          <div>
            <p class="label">Filigrane derrière la page</p>
            <OptionPills class="mt-1" label="Filigrane" :model-value="values.watermark_mode" :options="[{ value: 'none', label: 'Aucun' }, { value: 'logo', label: 'Logo de l’école' }, { value: 'image', label: 'Autre image' }, { value: 'text', label: 'Texte' }]" @update:model-value="set('watermark_mode', $event)" />
            <p v-if="values.watermark_mode === 'logo' && !school.logo" class="mt-1 text-xs text-warn-700">Aucun logo n’est enregistré : ajoutez-le dans l’onglet Identité.</p>
          </div>
          <div v-if="values.watermark_mode === 'image'" class="flex flex-col gap-2">
            <FormField v-model="values.watermark_image" :field="FILES.watermark_image" :error="errors.watermark_image" />
            <div v-if="images.watermark_image" class="flex items-center gap-3">
              <img :src="images.watermark_image" alt="Filigrane" class="size-12 rounded-lg border border-line bg-white object-contain" />
              <button type="button" class="btn btn-ghost btn-sm" @click="emit('remove-image', 'watermark_image')"><AppIcon name="trash" class="size-4" />Retirer</button>
            </div>
          </div>
          <FormField v-if="values.watermark_mode === 'text'" v-model="values.watermark_text" :field="{ key: 'watermark_text', label: 'Texte du filigrane', placeholder: 'Nom de l’école' }" :error="errors.watermark_text" />
          <template v-if="values.watermark_mode && values.watermark_mode !== 'none'">
            <div>
              <label for="wm-opacity" class="label">Intensité : {{ values.watermark_opacity || 8 }} %</label>
              <input id="wm-opacity" type="range" min="3" max="30" step="1" :value="values.watermark_opacity || 8" class="mt-2 w-full accent-brand-600" @input="set('watermark_opacity', Number($event.target.value))" />
              <p class="text-xs text-subtle">Un filigrane léger (5 à 10 %) laisse les notes lisibles.</p>
            </div>
            <div v-if="values.watermark_mode !== 'text'">
              <p class="label">Taille</p>
              <OptionPills class="mt-1" label="Taille du filigrane" :model-value="values.watermark_size" :options="[{ value: 'medium', label: 'Moyenne' }, { value: 'large', label: 'Grande' }]" @update:model-value="set('watermark_size', $event)" />
            </div>
          </template>
        </div>

        <!-- Signature (bas du bulletin : la mise en page ne change pas) -->
        <div v-if="section === 'signature'" class="space-y-5">
          <p class="text-sm text-muted">Imprimés en bas à droite du bulletin, à la place de la ligne de signature.</p>
          <FormField v-model="values.signature_title" :field="{ key: 'signature_title', label: 'Titre du signataire', placeholder: 'Le chef d’établissement' }" :error="errors.signature_title" />
          <FormField v-model="values.signatory_name" :field="{ key: 'signatory_name', label: 'Nom du signataire', placeholder: 'M. Koffi ADJOVI' }" :error="errors.signatory_name" />
          <div v-for="key in ['signature', 'stamp']" :key="key" class="flex flex-col gap-2">
            <FormField v-model="values[key]" :field="FILES[key]" :error="errors[key]" />
            <div v-if="images[key]" class="flex items-center gap-3">
              <img :src="images[key]" :alt="FILES[key].label" class="size-12 rounded-lg border border-line bg-white object-contain" />
              <button type="button" class="btn btn-ghost btn-sm" @click="emit('remove-image', key)"><AppIcon name="trash" class="size-4" />Retirer</button>
            </div>
          </div>
        </div>
      </fieldset>
    </div>

    <div class="space-y-3 lg:sticky lg:top-4 lg:self-start">
      <p class="text-xs font-semibold tracking-wide text-subtle uppercase">Aperçu</p>
      <ReportPagePreview :settings="values" :school="school" :images="images" />
      <button type="button" class="btn btn-secondary btn-sm w-full" @click="openFile('/settings/reports/preview')"><AppIcon name="file" class="size-4" />Aperçu PDF (réglages enregistrés)</button>
      <p class="text-center text-xs text-subtle">Enregistrez d’abord pour voir vos changements dans le PDF.</p>
    </div>
  </div>
</template>
