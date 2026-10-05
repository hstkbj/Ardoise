<script setup>
import { computed, watch } from 'vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import StatsCard from '@/components/ui/StatsCard.vue';
import BarChart from '@/components/ui/BarChart.vue';
import HBarList from '@/components/ui/HBarList.vue';
import ProgressBar from '@/components/ui/ProgressBar.vue';
import ErrorState from '@/components/ui/ErrorState.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import AppIcon from '@/components/ui/AppIcon.vue';
import PaymentTable from '@/components/domain/PaymentTable.vue';
import { dashboardApi } from '@/services/api';
import { createResource } from '@/services/resource';
import { useAsync } from '@/composables/useAsync';
import { useAuthStore } from '@/stores/auth';
import { useContextStore } from '@/stores/context';
import { formatDate, formatMoney, formatNumber } from '@/utils/format';

const auth = useAuthStore();
const ctx = useContextStore();
const params = () => ({ school_id: ctx.schoolId || undefined });

const { data, loading, error, run } = useAsync(() => dashboardApi.school(params()), { immediate: true });
const payments = useAsync(() => (auth.can('payments.view') ? createResource('payments').list({ per_page: 6, sort: '-paid_at', filter: { status: 'paid' } }).then((r) => r.data) : []), { immediate: true, initial: [] });
watch(() => ctx.schoolId, () => run());

const d = computed(() => data.value);
const collectedRate = computed(() => (d.value?.finance.expected ? Math.round((d.value.finance.collected / d.value.finance.expected) * 100) : 0));
const TONES = { danger: 'bg-danger-50 text-danger-600', warning: 'bg-warn-50 text-warn-700', neutral: 'bg-ground text-muted', info: 'bg-info-50 text-info-700' };
const greeting = new Date().getHours() < 18 ? 'Bonjour' : 'Bonsoir';
</script>

<template>
  <div class="space-y-6">
    <PageHeader :title="`${greeting}, ${auth.user?.name?.split(' ')[0] ?? ''}`" :subtitle="d?.term ? `Vue d’ensemble · ${d.term.name}` : 'Vue d’ensemble de l’établissement'">
      <template #actions>
        <RouterLink v-if="auth.can('students.create')" to="/admin/students/create" class="btn btn-secondary"><AppIcon name="plus" class="size-4" />Inscrire un élève</RouterLink>
        <RouterLink v-if="auth.can('payments.create')" to="/admin/payments/create" class="btn btn-primary"><AppIcon name="wallet" class="size-4" />Encaisser</RouterLink>
      </template>
    </PageHeader>

    <div v-if="error" class="card"><ErrorState :message="error.message" @retry="run" /></div>
    <template v-else>
      <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <StatsCard label="Élèves inscrits" :loading="loading" :value="formatNumber(d?.kpis.students)" :hint="d ? `+${d.kpis.new_students} ce mois` : ''" hint-tone="positive" />
        <StatsCard label="Enseignants" :loading="loading" :value="formatNumber(d?.kpis.teachers)" :hint="d ? `dont ${d.kpis.part_time_teachers} vacataire(s)` : ''" />
        <StatsCard label="Classes" :loading="loading" :value="formatNumber(d?.kpis.classes)" :hint="d ? `${d.kpis.schools} établissement(s)` : ''" />
        <StatsCard label="Parents" :loading="loading" :value="formatNumber(d?.kpis.parents)" :hint="d ? `${d.kpis.parents_activated_rate} % connectés` : ''" />
      </div>

      <div v-if="d?.alerts?.length" class="card divide-y divide-line-soft">
        <div v-for="(a, i) in d.alerts" :key="i" class="flex flex-wrap items-center gap-3 px-5 py-3">
          <span class="rounded-md px-2 py-0.5 text-xs font-semibold" :class="TONES[a.tone] || TONES.neutral">{{ a.label }}</span>
          <p class="min-w-0 flex-1 text-sm">{{ a.text }}</p>
          <RouterLink v-if="a.link" :to="a.link.to" class="text-sm link">{{ a.link.label }}</RouterLink>
        </div>
      </div>

      <div class="grid gap-4 lg:grid-cols-3">
        <section class="card p-5 lg:col-span-2">
          <div class="flex items-center justify-between"><h2 class="card-title">Moyennes par niveau</h2><span class="text-xs text-subtle">sur 20 · évaluations validées</span></div>
          <div class="mt-5"><BarChart v-if="!loading" :items="d?.averages_by_level ?? []" :max="20" :threshold="10" threshold-label="Moyenne" :format="(v) => Number(v).toLocaleString('fr-FR')" label="Moyennes par niveau" /><div v-else class="skeleton h-44" /></div>
        </section>
        <section class="card p-5">
          <h2 class="card-title">Recouvrement</h2>
          <template v-if="d">
            <p class="mt-4 text-3xl font-semibold tabular">{{ collectedRate }} %</p>
            <p class="text-sm text-muted">{{ formatMoney(d.finance.collected) }} encaissés sur {{ formatMoney(d.finance.expected) }}</p>
            <ProgressBar class="mt-4" :value="collectedRate" label="Taux de recouvrement" />
            <dl class="mt-5 space-y-2 text-sm">
              <div class="flex justify-between"><dt class="text-muted">Reste à percevoir</dt><dd class="font-medium tabular">{{ formatMoney(d.finance.pending) }}</dd></div>
              <div class="flex justify-between"><dt class="text-muted">Familles en retard</dt><dd class="font-medium tabular text-danger-600">{{ d.finance.late_families }}</dd></div>
            </dl>
            <RouterLink v-if="auth.can('payments.view')" to="/admin/payments" class="mt-5 inline-block text-sm link">Voir les paiements</RouterLink>
          </template>
          <div v-else class="skeleton mt-4 h-32" />
        </section>
      </div>

      <div class="grid gap-4 lg:grid-cols-3">
        <section class="card p-5">
          <h2 class="card-title">Absences du jour</h2>
          <template v-if="d">
            <p class="mt-4 text-3xl font-semibold tabular">{{ d.attendance_today.absent }}</p>
            <p class="text-sm text-muted">{{ d.attendance_today.rate }} % des élèves · {{ d.attendance_today.late }} retard(s) · {{ d.attendance_today.justified }} justifiée(s)</p>
            <div class="mt-5"><HBarList v-if="d.attendance_today.by_class.length" :items="d.attendance_today.by_class" :show-share="false" /><p v-else class="text-sm text-subtle">Aucune absence signalée aujourd’hui.</p></div>
          </template>
          <div v-else class="skeleton mt-4 h-32" />
        </section>
        <section class="card p-5">
          <h2 class="card-title">Élèves par établissement</h2>
          <div class="mt-5"><HBarList v-if="d" :items="d.students_by_school" /><div v-else class="skeleton h-32" /></div>
          <div v-if="d" class="mt-6 flex gap-4 text-sm">
            <div class="flex-1 rounded-lg bg-lavender p-3"><p class="text-xs text-muted">Filles</p><p class="font-semibold tabular">{{ d.gender.female }} %</p></div>
            <div class="flex-1 rounded-lg bg-sky p-3"><p class="text-xs text-muted">Garçons</p><p class="font-semibold tabular">{{ d.gender.male }} %</p></div>
          </div>
        </section>
        <section class="card p-5">
          <h2 class="card-title">Prochaines évaluations</h2>
          <ul v-if="d?.events?.length" class="mt-4 space-y-3">
            <li v-for="e in d.events" :key="e.date + e.title" class="flex gap-3">
              <span class="flex w-12 shrink-0 flex-col items-center rounded-lg bg-sun-soft py-1.5 text-warn-700"><span class="text-base leading-none font-semibold">{{ formatDate(e.date, { day: '2-digit' }) }}</span><span class="text-[10px] uppercase">{{ formatDate(e.date, { month: 'short' }) }}</span></span>
              <span class="min-w-0"><span class="block truncate text-sm font-medium">{{ e.title }}</span><span class="text-xs text-subtle">{{ e.place }}</span></span>
            </li>
          </ul>
          <EmptyState v-else-if="d" compact title="Rien de prévu" icon="calendar" />
          <div v-else class="skeleton mt-4 h-32" />
        </section>
      </div>

      <section v-if="auth.can('payments.view')" class="card">
        <div class="flex items-center justify-between px-5 pt-5 pb-3"><h2 class="card-title">Derniers encaissements</h2><RouterLink to="/admin/payments" class="text-sm link">Tout voir</RouterLink></div>
        <PaymentTable :payments="payments.data.value" :loading="payments.loading.value" />
      </section>
    </template>
  </div>
</template>
