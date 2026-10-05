<script setup>
/** Bulletins d'une classe pour une période : aperçu, génération, publication. */
import { computed, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import PageHeader from '@/components/ui/PageHeader.vue';
import FilterDropdown from '@/components/ui/FilterDropdown.vue';
import DataTable from '@/components/ui/DataTable.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import AppIcon from '@/components/ui/AppIcon.vue';
import { reportCardsApi } from '@/services/api';
import { OPTIONS } from '@/config/options';
import { useAuthStore } from '@/stores/auth';
import { useOptionsStore } from '@/stores/options';
import { useUiStore } from '@/stores/ui';

const router = useRouter();
const auth = useAuthStore();
const ui = useUiStore();
const opts = useOptionsStore();

const classId = ref(OPTIONS.classes[0]?.value ?? '');
const termId = ref(opts.current.term ?? '');
const rows = ref([]);
const meta = ref(null);
const loading = ref(false);
const error = ref(null);
const selected = ref([]);
const busy = ref(false);

async function load() {
  if (!classId.value) return;
  loading.value = true;
  error.value = null;
  try {
    const res = await reportCardsApi.list({ class_id: classId.value, term_id: termId.value || undefined });
    rows.value = res.data;
    meta.value = res.meta;
    selected.value = [];
  } catch (e) {
    error.value = e;
  } finally {
    loading.value = false;
  }
}
watch([classId, termId], load);
load();

const columns = [
  { key: 'rank', label: 'Rang', type: 'number' },
  { key: 'student_name', label: 'Élève', type: 'person', sub: 'matricule' },
  { key: 'general_average', label: 'Moyenne', type: 'grade', align: 'right' },
  { key: 'status', label: 'Statut', type: 'status' },
];

async function generate() {
  const ok = await ui.confirm({ title: 'Générer les bulletins ?', message: 'Les moyennes, rangs et appréciations sont calculés à partir des notes validées. Les bulletins existants de la période sont recalculés.', confirmLabel: 'Générer' });
  if (!ok) return;
  busy.value = true;
  try {
    const out = await reportCardsApi.generate({ class_id: classId.value, term_id: termId.value || undefined });
    ui.toast(out?.message || 'Bulletins générés.');
    load();
  } catch (e) {
    ui.error(e);
  } finally {
    busy.value = false;
  }
}

async function publish(ids) {
  const ok = await ui.confirm({ title: 'Publier les bulletins ?', message: `${ids.length} bulletin(s) seront visibles par les parents, qui recevront une notification.`, confirmLabel: 'Publier' });
  if (!ok) return;
  busy.value = true;
  try {
    const out = await reportCardsApi.publish(ids, termId.value || meta.value?.term?.id);
    ui.toast(out?.message || 'Bulletins publiés.');
    load();
  } catch (e) {
    ui.error(e);
  } finally {
    busy.value = false;
  }
}

const drafts = computed(() => rows.value.filter((r) => r.generated && r.status !== 'published').map((r) => r.id));
const showPath = (row) => ({ path: `/admin/report-cards/${row.id}`, query: { term_id: meta.value?.term?.id } });
</script>

<template>
  <div class="space-y-5">
    <PageHeader title="Bulletins" :subtitle="meta?.term ? `${meta.term.name}${meta.generated ? '' : ' · aperçu provisoire, non généré'}` : ''">
      <template v-if="auth.can('report_cards.create')" #actions>
        <button type="button" class="btn btn-secondary" :disabled="busy || !classId" @click="generate"><AppIcon name="refresh" class="size-4" />{{ meta?.generated ? 'Recalculer' : 'Générer' }}</button>
        <button v-if="auth.can('report_cards.publish')" type="button" class="btn btn-primary" :disabled="busy || !(selected.length || drafts.length)" @click="publish(selected.length ? selected : drafts)"><AppIcon name="send" class="size-4" />Publier {{ selected.length ? `(${selected.length})` : 'tout' }}</button>
      </template>
    </PageHeader>

    <div class="card grid gap-3 p-4 sm:grid-cols-3">
      <FilterDropdown v-model="classId" label="Classe" :options="OPTIONS.classes" all-label="Choisir une classe" />
      <FilterDropdown v-model="termId" label="Période" :options="OPTIONS.terms" all-label="Période en cours" />
    </div>

    <div class="card">
      <EmptyState v-if="!classId" title="Choisissez une classe" icon="layers" />
      <DataTable v-else v-model:selected="selected" :columns="columns" :rows="rows" :loading="loading" :error="error" :selectable="!!meta?.generated && auth.can('report_cards.publish')" empty-text="Aucun élève dans cette classe." @retry="load" @row-click="(row) => router.push(showPath(row))">
        <template #cell-rank="{ row }"><span class="tabular font-semibold">{{ row.rank ?? '—' }}</span></template>
        <template #actions="{ row }"><RouterLink :to="showPath(row)" class="btn btn-ghost btn-sm">Ouvrir</RouterLink></template>
      </DataTable>
    </div>
  </div>
</template>
