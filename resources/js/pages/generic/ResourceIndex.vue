<script setup>
/**
 * Page liste générique : recherche, filtres, tri, pagination, actions par ligne
 * et actions groupées, d'après config/resources.js.
 */
import { computed, useSlots } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import PageHeader from '@/components/ui/PageHeader.vue';
import DataTable from '@/components/ui/DataTable.vue';
import SearchInput from '@/components/ui/SearchInput.vue';
import FilterDropdown from '@/components/ui/FilterDropdown.vue';
import Pagination from '@/components/ui/Pagination.vue';
import RowActions from '@/components/ui/RowActions.vue';
import AppIcon from '@/components/ui/AppIcon.vue';
import { getResource } from '@/config/resources';
import { OPTIONS } from '@/config/options';
import { useResourceTable } from '@/composables/useResourceTable';
import { useAuthStore } from '@/stores/auth';
import { useUiStore } from '@/stores/ui';
import { paymentsApi } from '@/services/api';
import { openFile } from '@/utils/format';

const props = defineProps({
  resource: { type: String, required: true },
  base: { type: String, required: true },
  gradesPath: { type: String, default: '' },
  title: { type: String, default: '' },
});

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const ui = useUiStore();
const res = getResource(props.resource);

// Filtres initiaux depuis l'URL (?status=overdue&class_id=3)
const fromQuery = (v) => (v === undefined ? '' : /^\d+$/.test(v) ? Number(v) : v);
const initialFilters = Object.fromEntries(res.filters.map((f) => [f.key, f.options ? route.query[f.key] ?? '' : fromQuery(route.query[f.key])]));
const table = useResourceTable(res.endpoint, { initialFilters });
const { rows, meta, loading, error, search, page, sort, filters, selected } = table;

const perm = (action) => !res.permission || auth.can(`${res.permission}.${action}`);
const canCreate = computed(() => res.canCreate && !!res.form && perm('create'));

const BASE_ACTIONS = {
  view: { label: 'Voir', icon: 'eye' },
  edit: { label: 'Modifier', icon: 'edit', perm: 'update' },
  delete: { label: 'Supprimer', icon: 'trash', danger: true, perm: 'delete' },
  toggle: { label: 'Activer / désactiver', icon: 'refresh', perm: 'update' },
  grades: { label: 'Saisir les notes', icon: 'pencil' },
  receipt: { label: 'Reçu', icon: 'printer' },
  download: { label: 'Télécharger', icon: 'download' },
};

function visible(key, row) {
  if (key === 'set_active') return row.status !== 'active' && row.status !== 'closed';
  if (key === 'close') return row.status !== 'closed';
  if (key === 'suspend') return row.status !== 'suspended';
  if (key === 'activate') return row.status !== 'active';
  if (key === 'receipt') return Number(row.paid_amount) > 0;
  return true;
}

function actionsFor(row) {
  return res.rowActions
    .filter((key) => visible(key, row))
    .map((key) => {
      const def = res.customActions?.[key] ?? BASE_ACTIONS[key];
      if (!def) return null;
      if (def.perm && !perm(def.perm)) return null;
      if (key === 'grades' && !props.gradesPath) return null;
      if (key === 'toggle') return { key, ...def, label: row.status === 'active' ? 'Désactiver' : 'Activer' };
      return { key, ...def };
    })
    .filter(Boolean);
}

async function onAction(key, row) {
  try {
    switch (key) {
      case 'view': return router.push(`${props.base}/${row.id}`);
      case 'edit': return router.push(`${props.base}/${row.id}/edit`);
      case 'grades': return router.push({ path: props.gradesPath, query: { assessment: row.id } });
      case 'receipt': return openFile(paymentsApi.receiptPath(row.id));
      case 'download': return openFile(`/documents/${row.id}/download`);
      case 'delete': {
        const ok = await ui.confirm({ title: `Supprimer ${res.singular} ?`, message: `« ${row[res.titleField] ?? ''} » sera supprimé définitivement.`, confirmLabel: 'Supprimer', danger: true });
        if (!ok) return;
        await table.api.remove(row.id);
        ui.toast('Élément supprimé.');
        return table.load();
      }
      default: {
        const def = res.customActions?.[key];
        if (def?.confirm) {
          const ok = await ui.confirm({ title: def.label, message: def.confirm, confirmLabel: def.label, danger: !!def.danger });
          if (!ok) return;
        }
        const out = await table.api.action(row.id, key);
        ui.toast(out?.message || 'Action effectuée.');
        return table.load();
      }
    }
  } catch (e) {
    ui.error(e);
  }
}

function rowClick(row) {
  if (res.rowActions.includes('view')) router.push(`${props.base}/${row.id}`);
  else if (res.rowActions.includes('edit') && perm('update')) router.push(`${props.base}/${row.id}/edit`);
}

async function bulk(action) {
  const ok = await ui.confirm({ title: action.label, message: `${selected.value.length} élément(s) sélectionné(s).`, confirmLabel: action.label, danger: !!action.danger });
  if (!ok) return;
  try {
    const out = await table.api.bulk(action.key, selected.value);
    ui.toast(out?.message || 'Action effectuée.');
    selected.value = [];
    table.load();
  } catch (e) {
    ui.error(e);
  }
}

const filterOptions = (f) => f.options || OPTIONS[f.optionsKey] || [];
const slots = useSlots();
const cellSlots = Object.keys(slots).filter((n) => n.startsWith('cell-'));
const hasActions = computed(() => res.rowActions.length > 0);
</script>

<template>
  <div>
    <PageHeader :title="title || res.title" :subtitle="meta.total ? `${meta.total.toLocaleString('fr-FR')} au total` : ''">
      <template v-if="canCreate || $slots.actions" #actions>
        <slot name="actions" />
        <RouterLink v-if="canCreate" :to="`${base}/create`" class="btn btn-primary"><AppIcon name="plus" class="size-4" />{{ res.createLabel || 'Ajouter' }}</RouterLink>
      </template>
    </PageHeader>

    <slot name="before" />

    <div class="card">
      <div class="flex flex-wrap items-end gap-3 border-b border-line-soft p-4">
        <div class="min-w-56 flex-1"><SearchInput v-model="search" :placeholder="res.searchPlaceholder || 'Rechercher…'" /></div>
        <div v-for="f in res.filters" :key="f.key" class="w-full sm:w-44"><FilterDropdown v-model="filters[f.key]" :label="f.label" :options="filterOptions(f)" hide-label :all-label="`${f.label} : tous`" /></div>
        <button v-if="table.hasActiveFilters.value" type="button" class="btn btn-ghost" @click="table.resetFilters()"><AppIcon name="x" class="size-4" />Réinitialiser</button>
      </div>

      <div v-if="res.selectable && selected.length" class="flex flex-wrap items-center gap-2 border-b border-line-soft bg-brand-50 px-5 py-2.5 text-sm">
        <span class="font-medium">{{ selected.length }} sélectionné(s)</span>
        <button v-for="a in res.bulkActions" :key="a.key" type="button" class="btn btn-sm" :class="a.danger ? 'btn-danger' : 'btn-secondary'" @click="bulk(a)">{{ a.label }}</button>
        <button type="button" class="btn btn-sm btn-ghost" @click="selected = []">Annuler</button>
      </div>

      <DataTable
        v-model:selected="selected"
        :columns="res.columns"
        :rows="rows"
        :loading="loading"
        :error="error"
        :sort="sort"
        :selectable="!!res.selectable && perm('update')"
        :empty-text="res.emptyText"
        :caption="res.title"
        @sort="table.toggleSort"
        @retry="table.load"
        @row-click="rowClick"
      >
        <template v-for="name in cellSlots" :key="name" #[name]="slotProps"><slot :name="name" v-bind="slotProps" /></template>
        <template v-if="hasActions" #actions="{ row }">
          <RowActions v-if="actionsFor(row).length" :actions="actionsFor(row)" :label="`Actions pour ${row[res.titleField] ?? ''}`" @select="onAction($event, row)" />
        </template>
      </DataTable>
      <Pagination v-if="!loading && !error && rows.length" v-model="page" :meta="meta" />
    </div>
  </div>
</template>
