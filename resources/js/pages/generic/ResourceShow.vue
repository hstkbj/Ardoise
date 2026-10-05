<script setup>
/** Fiche détaillée générique : informations du formulaire, indicateurs et listes liées. */
import { computed, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import PageHeader from '@/components/ui/PageHeader.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import StatsCard from '@/components/ui/StatsCard.vue';
import DataTable from '@/components/ui/DataTable.vue';
import LoadingState from '@/components/ui/LoadingState.vue';
import ErrorState from '@/components/ui/ErrorState.vue';
import AppIcon from '@/components/ui/AppIcon.vue';
import { getResource } from '@/config/resources';
import { OPTIONS } from '@/config/options';
import { createResource } from '@/services/resource';
import { useAuthStore } from '@/stores/auth';
import { useUiStore } from '@/stores/ui';
import { formatDate, formatMoney, formatNumber } from '@/utils/format';

const props = defineProps({
  resource: { type: String, required: true },
  base: { type: String, required: true },
});

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const ui = useUiStore();
const res = getResource(props.resource);
const api = createResource(res.endpoint);
const id = route.params.id;

const item = ref(null);
const loading = ref(true);
const error = ref(null);
const related = ref([]);

async function load() {
  loading.value = true;
  error.value = null;
  try {
    item.value = await api.get(id);
    related.value = await Promise.all(
      (res.related || []).map(async (r) => {
        const rr = getResource(r.resource);
        const out = await createResource(rr.endpoint).list({ per_page: 50, filter: { [r.filterKey]: id } }).catch(() => ({ data: [] }));
        return { ...r, columns: rr.columns, rows: out.data ?? [], total: out.meta?.total ?? out.data?.length ?? 0 };
      }),
    );
  } catch (e) {
    error.value = e;
  } finally {
    loading.value = false;
  }
}
load();

const canEdit = computed(() => res.rowActions.includes('edit') && (!res.permission || auth.can(`${res.permission}.update`)));

function display(f) {
  const v = item.value?.[f.key];
  const opts = f.options || OPTIONS[f.optionsKey];
  if (f.key.endsWith('_id') && item.value?.[f.key.replace(/_id$/, '_name')]) return item.value[f.key.replace(/_id$/, '_name')];
  if (v === null || v === undefined || v === '' || (Array.isArray(v) && !v.length)) return '—';
  if (Array.isArray(v)) return v.map((x) => opts?.find((o) => String(o.value) === String(x))?.label ?? x).join(', ');
  if (opts) return opts.find((o) => String(o.value) === String(v))?.label ?? v;
  if (f.type === 'date') return formatDate(v);
  if (f.key.includes('amount') || f.key === 'price') return formatMoney(v);
  if (f.type === 'number') return formatNumber(v);
  return v;
}

const fields = computed(() => (res.form || []).flatMap((s) => s.fields).filter((f) => f.type !== 'file' && f.type !== 'password'));

async function suspendOrActivate(action) {
  try {
    const out = await api.action(id, action);
    ui.toast(out?.message || 'Statut mis à jour.');
    load();
  } catch (e) {
    ui.error(e);
  }
}
</script>

<template>
  <div>
    <LoadingState v-if="loading" variant="detail" />
    <div v-else-if="error" class="card"><ErrorState :message="error.message" @retry="load" /></div>
    <template v-else>
      <PageHeader :title="String(item[res.titleField] ?? '')" :breadcrumb="[{ label: res.title, to: base }, { label: String(item[res.titleField] ?? '') }]">
        <template #subtitle><StatusBadge v-if="item.status" :status="item.status" /></template>
        <template #actions>
          <template v-if="res.customActions?.suspend">
            <button v-if="item.status !== 'suspended'" type="button" class="btn btn-secondary" @click="suspendOrActivate('suspend')"><AppIcon name="lock" class="size-4" />Suspendre</button>
            <button v-else type="button" class="btn btn-secondary" @click="suspendOrActivate('activate')"><AppIcon name="unlock" class="size-4" />Activer</button>
          </template>
          <RouterLink v-if="canEdit" :to="`${base}/${id}/edit`" class="btn btn-primary"><AppIcon name="edit" class="size-4" />Modifier</RouterLink>
        </template>
      </PageHeader>

      <div v-if="res.stats?.length" class="mb-5 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <StatsCard v-for="s in res.stats" :key="s.key" :label="s.label" :value="formatNumber(item[s.key])" />
      </div>

      <slot :item="item" :reload="load" />

      <section class="card p-5 sm:p-6">
        <h2 class="card-title">Informations</h2>
        <dl class="mt-4 grid gap-x-8 gap-y-4 sm:grid-cols-2">
          <div v-for="f in fields" :key="f.key" :class="f.span === 2 ? 'sm:col-span-2' : ''">
            <dt class="text-xs text-subtle">{{ f.label }}</dt>
            <dd class="mt-0.5 text-sm whitespace-pre-line">{{ display(f) }}</dd>
          </div>
        </dl>
      </section>

      <section v-for="r in related" :key="r.title" class="card mt-5">
        <div class="flex items-center justify-between px-5 pt-5 pb-3">
          <h2 class="card-title">{{ r.title }} <span class="text-sm font-normal text-subtle">({{ r.total }})</span></h2>
          <RouterLink :to="{ path: r.base, query: { [r.filterKey]: id } }" class="text-sm link">Tout voir</RouterLink>
        </div>
        <DataTable :columns="r.columns.slice(0, 5)" :rows="r.rows" empty-text="Aucun élément." @row-click="(row) => router.push(`${r.base}/${row.id}`)" />
      </section>
    </template>
  </div>
</template>
