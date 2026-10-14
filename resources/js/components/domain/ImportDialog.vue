<script setup>
/**
 * Import d'un fichier Excel (.xlsx) ou CSV :
 * 1. télécharger le modèle, 2. choisir le fichier, 3. vérifier (aucun enregistrement),
 * 4. importer. Les lignes en erreur sont listées avec leur numéro.
 *
 *   <ImportDialog :open="…" endpoint="imports/students" template-path="/imports/students/template" @imported="reload" @close="…" />
 */
import { computed, ref, watch } from 'vue';
import BaseModal from '@/components/ui/BaseModal.vue';
import AppIcon from '@/components/ui/AppIcon.vue';
import { importsApi } from '@/services/api';
import { useUiStore } from '@/stores/ui';
import { openFile } from '@/utils/format';

const props = defineProps({
  open: { type: Boolean, default: false },
  title: { type: String, default: 'Importer un fichier' },
  endpoint: { type: String, required: true },
  templatePath: { type: String, default: '' },
  help: { type: String, default: '' },
  /** Case à cocher supplémentaire envoyée avec le fichier, ex. { key: 'notify', label: 'Prévenir les parents' } */
  option: { type: Object, default: null },
});
const emit = defineEmits(['close', 'imported']);
const ui = useUiStore();

const file = ref(null);
const optionValue = ref(false);
const checked = ref(null); // résultat de la vérification
const lines = ref([]);
const error = ref('');
const busy = ref(false);
const input = ref(null);

const fileLabel = computed(() => (file.value ? `${file.value.name} (${Math.max(1, Math.round(file.value.size / 1024))} Ko)` : ''));

watch(() => props.open, (open) => {
  if (open) reset();
});

function reset() {
  file.value = null;
  optionValue.value = false;
  checked.value = null;
  lines.value = [];
  error.value = '';
  if (input.value) input.value.value = '';
}

function pick(e) {
  file.value = e.target.files?.[0] ?? null;
  checked.value = null;
  lines.value = [];
  error.value = '';
}

async function send(dryRun) {
  if (!file.value) return (error.value = 'Choisissez un fichier.');
  busy.value = true;
  error.value = '';
  lines.value = [];
  try {
    const out = await importsApi.upload(props.endpoint, file.value, { dry_run: dryRun, ...(props.option ? { [props.option.key]: optionValue.value } : {}) });
    if (dryRun) {
      checked.value = out;
    } else {
      ui.toast(out.message);
      emit('imported', out.data);
      emit('close');
    }
  } catch (e) {
    error.value = e.message;
    lines.value = e.lines || [];
    checked.value = null;
  } finally {
    busy.value = false;
  }
}
</script>

<template>
  <BaseModal :open="open" :title="title" description="Fichier Excel (.xlsx) ou CSV. Tout le fichier est vérifié avant d’enregistrer : en cas d’erreur, rien n’est importé." size="lg" @close="emit('close')">
    <div class="space-y-4">
      <ol class="space-y-3 text-sm">
        <li v-if="templatePath" class="flex flex-wrap items-center gap-3">
          <span class="flex size-6 items-center justify-center rounded-full bg-brand-50 text-xs font-semibold text-brand-700">1</span>
          <span class="flex-1">Téléchargez le modèle et remplissez une ligne par élément (gardez la ligne d’en-têtes).</span>
          <button type="button" class="btn btn-sm btn-secondary" @click="openFile(templatePath)"><AppIcon name="download" class="size-4" />Modèle</button>
        </li>
        <li class="flex flex-wrap items-center gap-3">
          <span class="flex size-6 items-center justify-center rounded-full bg-brand-50 text-xs font-semibold text-brand-700">{{ templatePath ? 2 : 1 }}</span>
          <span class="flex-1">{{ fileLabel || 'Choisissez le fichier rempli.' }}</span>
          <label class="btn btn-sm btn-secondary cursor-pointer">
            <AppIcon name="upload" class="size-4" />Choisir…
            <input ref="input" type="file" class="sr-only" accept=".xlsx,.csv,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" @change="pick" />
          </label>
        </li>
      </ol>

      <p v-if="help" class="rounded-xl bg-ground p-3 text-[13px] text-muted">{{ help }}</p>

      <label v-if="option" class="flex items-center gap-2 text-sm text-body"><input v-model="optionValue" type="checkbox" class="size-4 accent-brand-600" />{{ option.label }}</label>

      <p v-if="checked" class="flex gap-2 rounded-xl bg-mint p-3 text-sm" role="status"><AppIcon name="check-circle" class="mt-0.5 size-4 shrink-0 text-brand-700" />{{ checked.message }}</p>

      <div v-if="error" class="space-y-2 rounded-xl bg-danger-50 p-3 text-sm text-danger-600" role="alert">
        <p class="font-medium">{{ error }}</p>
        <ul v-if="lines.length" class="max-h-56 space-y-1 overflow-y-auto text-[13px]">
          <li v-for="(l, i) in lines" :key="i"><b>Ligne {{ l.line }}</b> — {{ l.message }}</li>
        </ul>
      </div>

      <div class="flex flex-wrap justify-end gap-2 border-t border-line-soft pt-4">
        <button type="button" class="btn btn-ghost" @click="emit('close')">Annuler</button>
        <button type="button" class="btn btn-secondary" :disabled="busy || !file" @click="send(true)">Vérifier</button>
        <button type="button" class="btn btn-primary" :disabled="busy || !file" @click="send(false)">{{ busy ? 'Import…' : 'Importer' }}</button>
      </div>
    </div>
  </BaseModal>
</template>
