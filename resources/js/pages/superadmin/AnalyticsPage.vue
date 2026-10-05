<script setup>
import PageHeader from '@/components/ui/PageHeader.vue';
import StatsCard from '@/components/ui/StatsCard.vue';
import BarChart from '@/components/ui/BarChart.vue';
import ErrorState from '@/components/ui/ErrorState.vue';
import { dashboardApi } from '@/services/api';
import { useAsync } from '@/composables/useAsync';
import { formatMoney, formatNumber } from '@/utils/format';

const { data: d, loading, error, run } = useAsync(() => dashboardApi.platform(), { immediate: true });
const total = (list) => (list ?? []).reduce((a, i) => a + i.value, 0);
const short = (v) => (v >= 1e6 ? `${(v / 1e6).toLocaleString('fr-FR', { maximumFractionDigits: 1 })} M` : v >= 1e3 ? `${Math.round(v / 1e3)} k` : String(v));
</script>

<template>
  <div class="space-y-6">
    <PageHeader title="Statistiques" subtitle="Douze derniers mois. Les effectifs proviennent de la collecte quotidienne." />
    <div v-if="error" class="card"><ErrorState :message="error.message" @retry="run" /></div>
    <template v-else>
      <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <StatsCard label="Revenus (12 mois)" :loading="loading" :value="formatMoney(total(d?.revenue_by_month))" />
        <StatsCard label="Nouveaux tenants (12 mois)" :loading="loading" :value="formatNumber(total(d?.new_tenants_by_month))" />
        <StatsCard label="Élèves aujourd’hui" :loading="loading" :value="formatNumber(d?.kpis.students)" />
        <StatsCard label="Utilisateurs" :loading="loading" :value="formatNumber(d?.kpis.users)" />
      </div>
      <section class="card p-5"><h2 class="card-title">Revenus mensuels</h2><div class="mt-5"><BarChart v-if="d" :items="d.revenue_by_month" :format="short" label="Revenus mensuels" /><div v-else class="skeleton h-44" /></div></section>
      <section class="card p-5"><h2 class="card-title">Élèves gérés en fin de mois</h2><div class="mt-5"><BarChart v-if="d" :items="d.students_by_month" :format="short" label="Élèves gérés" /><div v-else class="skeleton h-44" /></div></section>
    </template>
  </div>
</template>
