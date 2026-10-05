<script setup>
import PageHeader from '@/components/ui/PageHeader.vue';
import StatsCard from '@/components/ui/StatsCard.vue';
import BarChart from '@/components/ui/BarChart.vue';
import HBarList from '@/components/ui/HBarList.vue';
import ErrorState from '@/components/ui/ErrorState.vue';
import AppIcon from '@/components/ui/AppIcon.vue';
import { dashboardApi } from '@/services/api';
import { useAsync } from '@/composables/useAsync';
import { formatMoney, formatNumber } from '@/utils/format';

const { data: d, loading, error, run } = useAsync(() => dashboardApi.platform(), { immediate: true });
const short = (v) => (v >= 1e6 ? `${(v / 1e6).toLocaleString('fr-FR', { maximumFractionDigits: 1 })} M` : v >= 1e3 ? `${Math.round(v / 1e3)} k` : String(v));
</script>

<template>
  <div class="space-y-6">
    <PageHeader title="Plateforme" subtitle="Vue d’ensemble des écoles clientes.">
      <template #actions><RouterLink to="/superadmin/tenants/create" class="btn btn-primary"><AppIcon name="plus" class="size-4" />Nouveau tenant</RouterLink></template>
    </PageHeader>
    <div v-if="error" class="card"><ErrorState :message="error.message" @retry="run" /></div>
    <template v-else>
      <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <StatsCard label="Tenants" :loading="loading" :value="formatNumber(d?.kpis.tenants)" :hint="d ? `${d.kpis.active} actifs · ${d.kpis.trial} en essai` : ''" />
        <StatsCard label="Élèves gérés" :loading="loading" :value="formatNumber(d?.kpis.students)" :hint="d ? `${formatNumber(d.kpis.teachers)} enseignants` : ''" />
        <StatsCard label="Revenu du mois" :loading="loading" :value="formatMoney(d?.kpis.revenue_month)" />
        <StatsCard label="Nouveaux tenants (30 j)" :loading="loading" :value="formatNumber(d?.kpis.new_tenants_30d)" :hint="d?.kpis.suspended ? `${d.kpis.suspended} suspendu(s)` : ''" hint-tone="warning" />
      </div>
      <div class="grid gap-4 lg:grid-cols-3">
        <section class="card p-5 lg:col-span-2"><h2 class="card-title">Revenus par mois</h2><div class="mt-5"><BarChart v-if="d" :items="d.revenue_by_month" :format="short" label="Revenus par mois" /><div v-else class="skeleton h-44" /></div></section>
        <section class="card p-5"><h2 class="card-title">Abonnements</h2><div class="mt-5"><HBarList v-if="d" :items="d.subscriptions_by_status" /><div v-else class="skeleton h-32" /></div></section>
      </div>
      <div class="grid gap-4 lg:grid-cols-2">
        <section class="card p-5"><h2 class="card-title">Nouveaux tenants</h2><div class="mt-5"><BarChart v-if="d" :items="d.new_tenants_by_month" label="Nouveaux tenants" /><div v-else class="skeleton h-44" /></div></section>
        <section class="card p-5"><h2 class="card-title">Utilisateurs actifs (7 jours)</h2><div class="mt-5"><BarChart v-if="d" :items="d.active_users_7d" label="Utilisateurs actifs" /><div v-else class="skeleton h-44" /></div></section>
      </div>
    </template>
  </div>
</template>
