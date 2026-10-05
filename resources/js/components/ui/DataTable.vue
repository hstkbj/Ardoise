<script setup>
/**
 * Tableau de données générique.
 * columns : [{ key, label, type?, sub?, sortable?, align?, optionsKey?, total? }]
 * Slots   : #cell-<key>="{ row, value }", #actions="{ row }", #empty
 */
import { computed } from 'vue';
import AppIcon from './AppIcon.vue';
import Avatar from './Avatar.vue';
import StatusBadge from './StatusBadge.vue';
import EmptyState from './EmptyState.vue';
import ErrorState from './ErrorState.vue';
import LoadingState from './LoadingState.vue';
import { formatDate, formatDateTime, formatMoney, formatNumber, get } from '@/utils/format';
import { optionLabel } from '@/config/options';

const props = defineProps({
  columns: { type: Array, required: true },
  rows: { type: Array, default: () => [] },
  rowKey: { type: String, default: 'id' },
  loading: { type: Boolean, default: false },
  error: { type: Object, default: null },
  sort: { type: String, default: '' },
  selectable: { type: Boolean, default: false },
  emptyTitle: { type: String, default: 'Aucun résultat' },
  emptyText: { type: String, default: '' },
  caption: { type: String, default: '' },
});
const selected = defineModel('selected', { type: Array, default: () => [] });
const emit = defineEmits(['sort', 'retry', 'row-click']);

const allChecked = computed(() => props.rows.length > 0 && props.rows.every((r) => selected.value.includes(r[props.rowKey])));

function toggleAll(e) {
  const ids = props.rows.map((r) => r[props.rowKey]);
  selected.value = e.target.checked ? [...new Set([...selected.value, ...ids])] : selected.value.filter((id) => !ids.includes(id));
}
function toggleOne(id) {
  selected.value = selected.value.includes(id) ? selected.value.filter((x) => x !== id) : [...selected.value, id];
}
function sortState(key) {
  if (props.sort === key) return 'ascending';
  if (props.sort === `-${key}`) return 'descending';
  return 'none';
}
function display(col, row) {
  const v = get(row, col.key);
  switch (col.type) {
    case 'money': return formatMoney(v);
    case 'date': return formatDate(v);
    case 'datetime': return v ? formatDateTime(v) : 'Jamais';
    case 'number': return formatNumber(v);
    case 'grade': return v == null ? '—' : Number(v).toLocaleString('fr-FR', { minimumFractionDigits: 1, maximumFractionDigits: 2 });
    case 'option': return optionLabel(col.optionsKey, v);
    case 'code': return v ? `••••-${v}` : '—';
    default: return v ?? '—';
  }
}
</script>

<template>
  <div>
    <LoadingState v-if="loading" :columns="Math.min(columns.length, 6)" />
    <ErrorState v-else-if="error" :message="error.message" @retry="emit('retry')" />
    <slot v-else-if="!rows.length" name="empty"><EmptyState :title="emptyTitle" :description="emptyText" icon="search" /></slot>

    <div v-else class="overflow-x-auto">
      <table class="w-full min-w-[720px] border-collapse text-sm">
        <caption v-if="caption" class="sr-only">{{ caption }}</caption>
        <thead>
          <tr class="bg-ground-soft text-left text-xs text-muted">
            <th v-if="selectable" scope="col" class="w-10 border-y border-line-soft py-3 pl-5">
              <input type="checkbox" class="size-4 accent-brand-600" :checked="allChecked" aria-label="Tout sélectionner" @change="toggleAll" />
            </th>
            <th v-for="col in columns" :key="col.key" scope="col" class="border-y border-line-soft px-3 py-3 font-medium first:pl-5 last:pr-5" :class="col.align === 'right' ? 'text-right' : ''" :aria-sort="col.sortable ? sortState(col.key) : undefined">
              <button v-if="col.sortable" type="button" class="inline-flex items-center gap-1 hover:text-ink" :class="sortState(col.key) !== 'none' ? 'text-ink' : ''" @click="emit('sort', col.key)">
                {{ col.label }}
                <AppIcon v-if="sortState(col.key) === 'ascending'" name="arrow-up" class="size-3.5" />
                <AppIcon v-else-if="sortState(col.key) === 'descending'" name="arrow-down" class="size-3.5" />
              </button>
              <template v-else>{{ col.label }}</template>
            </th>
            <th v-if="$slots.actions" scope="col" class="w-12 border-y border-line-soft py-3 pr-5"><span class="sr-only">Actions</span></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in rows" :key="row[rowKey]" class="border-b border-line-soft last:border-b-0 hover:bg-ground-soft" :class="{ 'bg-brand-50': selectable && selected.includes(row[rowKey]) }">
            <td v-if="selectable" class="py-3 pl-5">
              <input type="checkbox" class="size-4 accent-brand-600" :checked="selected.includes(row[rowKey])" :aria-label="`Sélectionner la ligne ${row[rowKey]}`" @change="toggleOne(row[rowKey])" />
            </td>
            <td v-for="(col, ci) in columns" :key="col.key" class="px-3 py-3 first:pl-5 last:pr-5" :class="[col.align === 'right' ? 'text-right tabular' : '', ci === 0 ? 'font-medium' : '']">
              <slot :name="`cell-${col.key}`" :row="row" :value="get(row, col.key)">
                <StatusBadge v-if="col.type === 'status'" :status="get(row, col.key)" />
                <div v-else-if="col.type === 'person'" class="flex items-center gap-3">
                  <Avatar :name="String(get(row, col.key) ?? '')" />
                  <div class="min-w-0">
                    <button v-if="ci === 0" type="button" class="truncate text-left font-medium hover:text-brand-700" @click="emit('row-click', row)">{{ get(row, col.key) }}</button>
                    <div v-else class="truncate font-medium">{{ get(row, col.key) }}</div>
                    <div v-if="col.sub" class="truncate text-xs font-normal text-subtle">{{ get(row, col.sub) }}</div>
                  </div>
                </div>
                <div v-else-if="col.type === 'progress'" class="min-w-28">
                  <div class="text-xs tabular text-muted">{{ get(row, col.key) }} / {{ get(row, col.total) }}</div>
                  <div class="mt-1 h-1.5 rounded-full bg-line-soft">
                    <div class="h-1.5 rounded-full bg-brand-600" :style="{ width: `${Math.min(100, Math.round((get(row, col.key) / (get(row, col.total) || 1)) * 100))}%` }" />
                  </div>
                </div>
                <span v-else-if="col.type === 'capacity'" class="tabular" :class="row.capacity && row.students_count >= row.capacity ? 'font-semibold text-warn-700' : ''">
                  {{ row.students_count }}<span v-if="row.capacity" class="text-subtle"> / {{ row.capacity }}</span>
                </span>
                <code v-else-if="col.type === 'code'" class="text-xs text-muted">{{ display(col, row) }}</code>
                <template v-else>{{ display(col, row) }}</template>
              </slot>
            </td>
            <td v-if="$slots.actions" class="py-2 pr-5 text-right"><slot name="actions" :row="row" /></td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
