<script setup>
/** Historique des absences et retards, avec justification. */
import { reactive, ref, watch } from 'vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import DataTable from '@/components/ui/DataTable.vue';
import Pagination from '@/components/ui/Pagination.vue';
import SearchInput from '@/components/ui/SearchInput.vue';
import FilterDropdown from '@/components/ui/FilterDropdown.vue';
import DateRangePicker from '@/components/ui/DateRangePicker.vue';
import BaseModal from '@/components/ui/BaseModal.vue';
import { attendanceApi } from '@/services/api';
import { OPTIONS } from '@/config/options';
import { useAuthStore } from '@/stores/auth';
import { useUiStore } from '@/stores/ui';

const props = defineProps({ backPath: { type: String, default: '/admin/attendance' } });
const auth = useAuthStore();
const ui = useUiStore();

const rows = ref([]);
const meta = ref({ current_page: 1, per_page: 20, total: 0, last_page: 1 });
const loading = ref(true);
const error = ref(null);
const page = ref(1);
const search = ref('');
const filters = reactive({ class_id: '', status: '', justified: '' });
const range = ref({ from: '', to: '' });

let timer;
async function load() {
  loading.value = true;
  error.value = null;
  try {
    const filter = Object.fromEntries(Object.entries({ ...filters, from: range.value.from, to: range.value.to }).filter(([, v]) => v !== ''));
    const res = await attendanceApi.history({ page: page.value, per_page: 20, search: search.value || undefined, filter });
    rows.value = res.data;
    meta.value = res.meta;
  } catch (e) {
    error.value = e;
  } finally {
    loading.value = false;
  }
}
watch(search, () => {
  clearTimeout(timer);
  timer = setTimeout(() => (page.value === 1 ? load() : (page.value = 1)), 300);
});
watch([() => ({ ...filters }), range], () => (page.value === 1 ? load() : (page.value = 1)), { deep: true });
watch(page, load);
load();

const columns = [
  { key: 'date', label: 'Date', type: 'date', sortable: false },
  { key: 'slot', label: 'Séance' },
  { key: 'student_name', label: 'Élève', type: 'person', sub: 'class_name' },
  { key: 'status', label: 'Type', type: 'status' },
  { key: 'minutes', label: 'Retard (min)', type: 'number', align: 'right' },
  { key: 'justified', label: 'Justification' },
];

const justifying = ref(null);
const reason = ref('');
async function justify() {
  try {
    const out = await attendanceApi.justify(justifying.value.id, reason.value);
    Object.assign(justifying.value, out.data);
    ui.toast('Absence justifiée.');
    justifying.value = null;
  } catch (e) {
    ui.error(e);
  }
}
</script>

<template>
  <div class="space-y-5">
    <PageHeader title="Historique des absences" :breadcrumb="[{ label: 'Appel', to: backPath }, { label: 'Historique' }]" />
    <div class="card">
      <div class="flex flex-wrap items-end gap-3 border-b border-line-soft p-4">
        <div class="min-w-56 flex-1"><SearchInput v-model="search" placeholder="Nom ou matricule…" /></div>
        <div class="w-40"><FilterDropdown v-model="filters.class_id" label="Classe" :options="OPTIONS.classes" hide-label all-label="Toutes les classes" /></div>
        <div class="w-36"><FilterDropdown v-model="filters.status" label="Type" :options="[{ value: 'absent', label: 'Absences' }, { value: 'late', label: 'Retards' }]" hide-label all-label="Absences et retards" /></div>
        <div class="w-36"><FilterDropdown v-model="filters.justified" label="Justification" :options="[{ value: '1', label: 'Justifiées' }, { value: '0', label: 'Non justifiées' }]" hide-label all-label="Toutes" /></div>
        <DateRangePicker v-model="range" label="Période" />
      </div>
      <DataTable :columns="columns" :rows="rows" :loading="loading" :error="error" empty-text="Aucune absence pour ces critères." @retry="load">
        <template #cell-justified="{ row }">
          <span v-if="row.justified" class="text-sm text-brand-700">Justifiée{{ row.reason ? ` · ${row.reason}` : '' }}</span>
          <span v-else class="text-sm text-subtle">Non justifiée</span>
        </template>
        <template #actions="{ row }">
          <button v-if="!row.justified && auth.can('attendance.update')" type="button" class="btn btn-secondary btn-sm" @click="justifying = row; reason = ''">Justifier</button>
        </template>
      </DataTable>
      <Pagination v-if="rows.length" v-model="page" :meta="meta" label="enregistrements" />
    </div>

    <BaseModal :open="!!justifying" title="Justifier l’absence" :description="justifying ? `${justifying.student_name} · ${justifying.date}` : ''" @close="justifying = null">
      <label for="just-reason" class="label">Motif</label>
      <input id="just-reason" v-model="reason" class="input mt-1" placeholder="Certificat médical, rendez-vous…" />
      <template #footer>
        <button type="button" class="btn btn-secondary" @click="justifying = null">Annuler</button>
        <button type="button" class="btn btn-primary" @click="justify">Justifier</button>
      </template>
    </BaseModal>
  </div>
</template>
