<script setup>
/** Paiements : on choisit une classe, puis un élève pour voir ses frais et ce qu'il lui reste à payer. */
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import PageHeader from '@/components/ui/PageHeader.vue';
import StatsCard from '@/components/ui/StatsCard.vue';
import DataTable from '@/components/ui/DataTable.vue';
import AppIcon from '@/components/ui/AppIcon.vue';
import { paymentsApi } from '@/services/api';
import { OPTIONS } from '@/config/options';
import { useAsync } from '@/composables/useAsync';
import { useAuthStore } from '@/stores/auth';
import { useContextStore } from '@/stores/context';
import { useUiStore } from '@/stores/ui';
import { formatMoney } from '@/utils/format';

const auth = useAuthStore();
const ctx = useContextStore();
const ui = useUiStore();
const route = useRoute();
const router = useRouter();

// Synthèse de l'année (en haut de page)
const { data: s, loading: summaryLoading, run: loadSummary } = useAsync(() => paymentsApi.summary({ school_id: ctx.schoolId || undefined }), { immediate: true });
watch(() => ctx.schoolId, () => loadSummary());

// Classes proposées (selon l'établissement choisi dans l'en-tête), classe retenue dans l'adresse pour garder le choix au retour
const classes = computed(() => OPTIONS.classes.filter((c) => !ctx.schoolId || c.school_id === Number(ctx.schoolId)));
const queryClass = Number(route.query.class_id) || null;
const classId = ref(classes.value.some((c) => c.value === queryClass) ? queryClass : classes.value[0]?.value ?? '');
const search = ref('');
const status = ref('');
const rows = ref([]);
const total = ref(0);
const loading = ref(false);
const error = ref(null);

async function load() {
  if (!classId.value) {
    rows.value = [];
    return;
  }
  loading.value = true;
  error.value = null;
  try {
    const out = await paymentsApi.students({ per_page: 200, sort: 'name', search: search.value || undefined, filter: { class_id: classId.value, status: status.value || undefined } });
    rows.value = out.data;
    total.value = out.meta?.total ?? out.data.length;
  } catch (e) {
    error.value = e;
  } finally {
    loading.value = false;
  }
}

let timer;
watch(search, () => {
  clearTimeout(timer);
  timer = setTimeout(load, 300);
});
onBeforeUnmount(() => clearTimeout(timer));
watch([classId, status], () => {
  router.replace({ query: { ...route.query, class_id: classId.value || undefined } });
  load();
});
watch(classes, (list) => {
  if (!list.some((c) => c.value === classId.value)) classId.value = list[0]?.value ?? '';
});
load();

const columns = [
  { key: 'full_name', label: 'Élève', type: 'person', sub: 'matricule' },
  { key: 'expected', label: 'Total à payer', type: 'money', align: 'right' },
  { key: 'paid', label: 'Déjà payé', type: 'money', align: 'right' },
  { key: 'remaining', label: 'Reste à payer', type: 'money', align: 'right' },
  { key: 'status', label: 'Situation', type: 'status' },
];
const totals = computed(() => rows.value.reduce((t, r) => ({ expected: t.expected + r.expected, paid: t.paid + r.paid, remaining: t.remaining + r.remaining }), { expected: 0, paid: 0, remaining: 0 }));
const className = computed(() => classes.value.find((c) => c.value === classId.value)?.label ?? '');

function open(row) {
  router.push(`/admin/payments/student/${row.id}`);
}
</script>

<template>
  <div class="space-y-5">
    <PageHeader title="Paiements" subtitle="Choisissez une classe, puis un élève pour voir ses frais et ce qu’il lui reste à payer.">
      <template #actions>
        <RouterLink to="/admin/payments/records" class="btn btn-secondary"><AppIcon name="list" class="size-4" />Échéances et reçus</RouterLink>
        <RouterLink v-if="auth.can('payments.create')" to="/admin/payments/create" class="btn btn-primary"><AppIcon name="wallet" class="size-4" />Enregistrer un paiement</RouterLink>
      </template>
    </PageHeader>

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-3 xl:grid-cols-6">
      <StatsCard label="Attendu sur l’année" :loading="summaryLoading" :value="formatMoney(s?.expected)" />
      <StatsCard label="Encaissé" :loading="summaryLoading" :value="formatMoney(s?.collected)" :hint="s?.expected ? `${Math.round((s.collected / s.expected) * 100)} % de l’attendu` : ''" hint-tone="positive" />
      <StatsCard label="Reste total à payer" :loading="summaryLoading" :value="formatMoney(s?.remaining)" :hint="s ? `${s.overdue_count} échéance(s) en retard` : ''" hint-tone="warning" />
      <StatsCard label="Échéances à venir (30 jours)" :loading="summaryLoading" :value="formatMoney(s?.due_soon_amount)" :hint="s ? `${s.due_soon_count} échéance(s)` : ''" />
      <StatsCard label="En retard" :loading="summaryLoading" :value="formatMoney(s?.overdue_amount)" :hint="s ? `${s.overdue_count} échéance(s)` : ''" hint-tone="warning" />
      <StatsCard label="Paiements en ligne à confirmer" :loading="summaryLoading" :value="s?.pending_online ?? 0" />
    </div>
    <p v-if="s?.pending_online" class="rounded-xl bg-warn-50 px-4 py-3 text-sm text-warn-700">
      {{ s.pending_online }} paiement(s) en ligne attendent votre confirmation.
      <RouterLink :to="{ path: '/admin/payments/records', query: { has_pending: '1' } }" class="font-semibold underline">Les afficher</RouterLink>
    </p>

    <div class="card grid gap-3 p-4 sm:grid-cols-3">
      <div>
        <label for="pay-class" class="label">Classe</label>
        <select id="pay-class" v-model="classId" class="input mt-1">
          <option v-if="!classes.length" value="">Aucune classe</option>
          <option v-for="c in classes" :key="c.value" :value="c.value">{{ c.label }}{{ c.school ? ` · ${c.school}` : '' }}</option>
        </select>
      </div>
      <div>
        <label for="pay-status" class="label">Situation</label>
        <select id="pay-status" v-model="status" class="input mt-1">
          <option value="">Tous les élèves</option>
          <option value="remaining">Reste à payer</option>
          <option value="late">En retard</option>
          <option value="settled">Soldés</option>
          <option value="none">Sans frais</option>
        </select>
      </div>
      <div>
        <label for="pay-search" class="label">Rechercher</label>
        <input id="pay-search" v-model="search" type="search" class="input mt-1" placeholder="Nom ou matricule…" />
      </div>
    </div>

    <div class="card">
      <DataTable :columns="columns" :rows="rows" :loading="loading" :error="error" :caption="`Élèves de ${className}`" empty-title="Aucun élève" empty-text="Aucun élève ne correspond à cette classe et à ces critères." @retry="load" @row-click="open" />
      <div v-if="rows.length" class="flex flex-wrap items-center justify-between gap-3 border-t border-line-soft px-5 py-3 text-sm">
        <span class="text-muted">{{ total }} élève(s) · Cliquez sur un élève pour voir le détail de ses frais.</span>
        <span class="tabular"><b>{{ formatMoney(totals.paid) }}</b> payés sur {{ formatMoney(totals.expected) }} · reste <b>{{ formatMoney(totals.remaining) }}</b></span>
      </div>
    </div>
  </div>
</template>
