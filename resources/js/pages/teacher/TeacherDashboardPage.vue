<script setup>
import PageHeader from '@/components/ui/PageHeader.vue';
import StatsCard from '@/components/ui/StatsCard.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import ErrorState from '@/components/ui/ErrorState.vue';
import LoadingState from '@/components/ui/LoadingState.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import AppIcon from '@/components/ui/AppIcon.vue';
import { dashboardApi } from '@/services/api';
import { useAsync } from '@/composables/useAsync';
import { useAuthStore } from '@/stores/auth';
import { formatDate } from '@/utils/format';

const auth = useAuthStore();
const { data: d, loading, error, run } = useAsync(() => dashboardApi.teacher(), { immediate: true });
const todayLabel = new Date().toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' });
</script>

<template>
  <div class="space-y-6">
    <PageHeader :title="`Bonjour, ${auth.user?.name?.split(' ')[0] ?? ''}`" :subtitle="`Nous sommes ${todayLabel}.`">
      <template #actions>
        <RouterLink to="/teacher/attendance" class="btn btn-secondary"><AppIcon name="check-circle" class="size-4" />Faire l’appel</RouterLink>
        <RouterLink to="/teacher/grades" class="btn btn-primary"><AppIcon name="pencil" class="size-4" />Saisir des notes</RouterLink>
      </template>
    </PageHeader>

    <LoadingState v-if="loading" variant="cards" :rows="4" />
    <div v-else-if="error" class="card"><ErrorState :message="error.message" @retry="run" /></div>
    <template v-else-if="d">
      <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <StatsCard label="Mes classes" :value="d.stats.classes" />
        <StatsCard label="Mes élèves" :value="d.stats.students" />
        <StatsCard label="Évaluations de la période" :value="d.stats.assessments" />
        <StatsCard label="Absences cette semaine" :value="d.stats.absences_week" />
      </div>

      <div class="grid gap-5 lg:grid-cols-2">
        <section class="card">
          <h2 class="card-title px-5 pt-5 pb-3">Mes cours aujourd’hui</h2>
          <EmptyState v-if="!d.today.length" compact title="Pas de cours aujourd’hui" icon="calendar" />
          <ul v-else class="divide-y divide-line-soft">
            <li v-for="c in d.today" :key="c.time + c.class_id" class="flex items-center gap-3 px-5 py-3">
              <span class="w-24 shrink-0 text-sm font-medium tabular">{{ c.time }}</span>
              <span class="min-w-0 flex-1"><span class="block truncate text-sm font-medium">{{ c.subject }} · {{ c.class_name }}</span><span class="text-xs text-subtle">{{ c.room || 'Salle non précisée' }}</span></span>
              <StatusBadge v-if="c.attendance_done" status="entered" label="Appel fait" />
              <RouterLink v-else to="/teacher/attendance" class="btn btn-secondary btn-sm">Appel</RouterLink>
            </li>
          </ul>
        </section>

        <section class="card">
          <h2 class="card-title px-5 pt-5 pb-3">Notes à saisir</h2>
          <EmptyState v-if="!d.to_grade.length" compact title="Tout est à jour" icon="check" />
          <ul v-else class="divide-y divide-line-soft">
            <li v-for="a in d.to_grade" :key="a.id">
              <RouterLink :to="{ path: '/teacher/grades', query: { assessment: a.id } }" class="flex items-center gap-3 px-5 py-3 hover:bg-ground-soft">
                <span class="min-w-0 flex-1"><span class="block truncate text-sm font-medium">{{ a.title }}</span><span class="text-xs text-subtle">{{ a.class_name }} · à valider avant le {{ formatDate(a.due) }}</span></span>
                <span class="text-sm tabular text-muted">{{ a.graded }}/{{ a.total }}</span>
                <AppIcon name="chevron-right" class="size-4 text-subtle" />
              </RouterLink>
            </li>
          </ul>
        </section>
      </div>

      <section class="card">
        <div class="flex items-center justify-between px-5 pt-5 pb-3"><h2 class="card-title">Devoirs récents</h2><RouterLink to="/teacher/homework/create" class="text-sm link">Donner un devoir</RouterLink></div>
        <EmptyState v-if="!d.homework.length" compact title="Aucun devoir en cours" icon="list" />
        <ul v-else class="divide-y divide-line-soft">
          <li v-for="h in d.homework" :key="h.id" class="flex items-center gap-3 px-5 py-3">
            <span class="min-w-0 flex-1"><span class="block truncate text-sm font-medium">{{ h.title }}</span><span class="text-xs text-subtle">{{ h.class_name }} · pour le {{ formatDate(h.due) }}</span></span>
            <StatusBadge :status="h.status" />
          </li>
        </ul>
      </section>
    </template>
  </div>
</template>
