<script setup>
/** Réinscription : les élèves déjà dans l'établissement passent dans leur classe de l'année courante. */
import { computed, reactive, ref, watch } from 'vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import LoadingState from '@/components/ui/LoadingState.vue';
import ErrorState from '@/components/ui/ErrorState.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import AppIcon from '@/components/ui/AppIcon.vue';
import { reenrollmentApi } from '@/services/api';
import { OPTIONS } from '@/config/options';
import { useUiStore } from '@/stores/ui';

const ui = useUiStore();

const schoolId = ref('');
const fromClassId = ref('');
const year = ref(null);
const feesDefined = ref(true);
const rows = ref([]);
const target = reactive({}); // student_id -> classe de destination
const selected = reactive({}); // student_id -> coché
const loading = ref(false);
const saving = ref(false);
const error = ref(null);

const fromClasses = computed(() => OPTIONS.classes.filter((c) => !schoolId.value || c.school_id === Number(schoolId.value)));
const selectedRows = computed(() => rows.value.filter((r) => selected[r.student_id]));
const chosen = computed(() => selectedRows.value.filter((r) => target[r.student_id]));
const canSubmit = computed(() => selectedRows.value.length > 0 && selectedRows.value.length === chosen.value.length && !saving.value);
const allChecked = computed(() => rows.value.length > 0 && rows.value.every((r) => selected[r.student_id]));

async function load() {
  loading.value = true;
  error.value = null;
  try {
    const out = await reenrollmentApi.pending({ school_id: schoolId.value || undefined, class_id: fromClassId.value || undefined });
    year.value = out.year;
    feesDefined.value = out.fees_defined;
    rows.value = out.students;
    rows.value.forEach((r) => {
      target[r.student_id] = r.suggested_class_id ?? '';
      selected[r.student_id] = !!r.suggested_class_id;
    });
  } catch (e) {
    error.value = e;
  } finally {
    loading.value = false;
  }
}

function toggleAll(checked) {
  rows.value.forEach((r) => {
    selected[r.student_id] = checked;
  });
}

async function submit() {
  if (!canSubmit.value) return;
  saving.value = true;
  try {
    const out = await reenrollmentApi.submit(chosen.value.map((r) => ({ student_id: r.student_id, class_id: Number(target[r.student_id]) })));
    ui.toast(out?.message || 'Réinscription enregistrée.');
    await load();
  } catch (e) {
    ui.error(e);
  } finally {
    saving.value = false;
  }
}

watch(schoolId, () => {
  if (fromClassId.value && !fromClasses.value.some((c) => c.value === fromClassId.value)) fromClassId.value = '';
});
watch([schoolId, fromClassId], load, { immediate: true });
</script>

<template>
  <div>
    <PageHeader title="Réinscription" :subtitle="year ? `Passage des élèves dans leur classe de l’année ${year.name}` : 'Passage des élèves dans leur classe de l’année suivante'" :breadcrumb="[{ label: 'Scolarité' }, { label: 'Réinscription' }]">
      <template #actions>
        <button type="button" class="btn btn-primary" :disabled="!canSubmit" @click="submit"><AppIcon name="check" class="size-4" />Réinscrire ({{ selectedRows.length }})</button>
      </template>
    </PageHeader>

    <div v-if="!feesDefined" class="card mb-4 border-warning/40 bg-warning-soft px-5 py-3 text-sm">
      Aucun frais n’est défini pour cette année. Créez-les avant de réinscrire, afin que les échéances soient générées pour chaque élève.
    </div>

    <div class="card mb-4 grid gap-3 p-4 sm:grid-cols-2">
      <div>
        <label for="re-school" class="label">Établissement</label>
        <select id="re-school" v-model="schoolId" class="input mt-1"><option value="">Tous</option><option v-for="s in OPTIONS.schools" :key="s.value" :value="s.value">{{ s.label }}</option></select>
      </div>
      <div>
        <label for="re-class" class="label">Classe de l’année précédente</label>
        <select id="re-class" v-model="fromClassId" class="input mt-1"><option value="">Toutes</option><option v-for="c in fromClasses" :key="c.value" :value="c.value">{{ c.label }}</option></select>
      </div>
    </div>

    <LoadingState v-if="loading && !rows.length" />
    <div v-else-if="error" class="card"><ErrorState :message="error.message" @retry="load" /></div>
    <div v-else-if="!rows.length" class="card"><EmptyState title="Aucun élève à réinscrire" description="Tous les élèves actifs sont déjà inscrits dans l’année courante." icon="users" /></div>
    <div v-else class="card overflow-x-auto">
      <p class="px-4 pt-4 text-xs text-subtle">Cochez les élèves à réinscrire, puis choisissez une nouvelle classe pour chacun. Le bouton s’activera quand toutes les lignes cochées auront une destination.</p>
      <table class="w-full text-sm">
        <thead>
          <tr class="text-left text-subtle">
            <th class="w-10 px-4 py-3"><input type="checkbox" :checked="allChecked" aria-label="Tout sélectionner" @change="toggleAll($event.target.checked)" /></th>
            <th class="px-4 py-3">Élève</th>
            <th class="px-4 py-3">Classe précédente</th>
            <th class="px-4 py-3">Nouvelle classe</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-line-soft">
          <tr v-for="r in rows" :key="r.student_id">
            <td class="px-4 py-2"><input v-model="selected[r.student_id]" type="checkbox" :aria-label="`Réinscrire ${r.full_name}`" /></td>
            <td class="px-4 py-2"><span class="font-medium">{{ r.full_name }}</span><span class="ml-2 text-xs text-subtle">{{ r.matricule }}</span></td>
            <td class="px-4 py-2">{{ r.from_class ?? '—' }}</td>
            <td class="px-4 py-2">
              <select v-model="target[r.student_id]" class="input" :aria-label="`Nouvelle classe de ${r.full_name}`" @change="selected[r.student_id] = !!target[r.student_id]">
                <option value="">— Choisir —</option>
                <option v-for="c in OPTIONS.classes" :key="c.value" :value="c.value">{{ c.label }}{{ c.school ? ` · ${c.school}` : '' }}</option>
              </select>
            </td>
          </tr>
        </tbody>
      </table>
      <p class="px-4 py-3 text-xs text-subtle">La classe du niveau suivant est proposée. Pour un redoublement, choisissez la même classe ; les frais de l’année sont assignés automatiquement.</p>
    </div>
  </div>
</template>
