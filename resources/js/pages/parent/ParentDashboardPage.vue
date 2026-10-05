<script setup>
import { computed } from 'vue';
import ParentSection from '@/components/domain/ParentSection.vue';
import BarChart from '@/components/ui/BarChart.vue';
import AppIcon from '@/components/ui/AppIcon.vue';
import { parentApi } from '@/services/api';
import { useAsync } from '@/composables/useAsync';
import { useAuthStore } from '@/stores/auth';
import { formatDate, formatMoney, formatScore } from '@/utils/format';

const auth = useAuthStore();
const news = useAsync(() => parentApi.announcements(), { immediate: true, initial: [] });
const firstName = computed(() => auth.user?.name?.split(' ')[0] ?? '');
const due = (child) => child.payments.filter((p) => p.status !== 'paid').reduce((a, p) => a + Number(p.remaining), 0);
const todo = (child) => child.homework.filter((h) => !h.done).slice(0, 3);
</script>

<template>
  <ParentSection :title="`Bonjour ${firstName}`" wide>
    <template #default="{ child }">
      <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <RouterLink to="/parent/grades" class="rounded-2xl bg-mint p-4">
          <p class="text-xs text-muted">Moyenne · {{ child.term || 'période' }}</p>
          <p class="mt-1 text-2xl font-semibold tabular">{{ formatScore(child.general_average) }}<span class="text-sm text-subtle">/20</span></p>
          <p v-if="child.rank" class="text-xs text-brand-700">{{ child.rank }}<sup>{{ child.rank === 1 ? 'er' : 'e' }}</sup> sur {{ child.class_size }}</p>
        </RouterLink>
        <RouterLink to="/parent/attendance" class="rounded-2xl bg-blush p-4">
          <p class="text-xs text-muted">Absences</p>
          <p class="mt-1 text-2xl font-semibold tabular">{{ child.absences }}</p>
          <p class="text-xs text-muted">{{ child.late }} retard(s)</p>
        </RouterLink>
        <RouterLink to="/parent/homework" class="rounded-2xl bg-sky p-4">
          <p class="text-xs text-muted">Devoirs à faire</p>
          <p class="mt-1 text-2xl font-semibold tabular">{{ child.homework.filter((h) => !h.done).length }}</p>
        </RouterLink>
        <RouterLink to="/parent/payments" class="rounded-2xl bg-sun-soft p-4">
          <p class="text-xs text-muted">Reste à payer</p>
          <p class="mt-1 text-lg font-semibold tabular">{{ formatMoney(due(child)) }}</p>
        </RouterLink>
      </div>

      <section class="card p-5">
        <div class="flex items-center justify-between"><h2 class="card-title">Évolution des notes</h2><span class="text-xs text-subtle">ramenées sur 20</span></div>
        <div class="mt-4"><BarChart :items="child.evolution" :max="20" :threshold="10" :height="140" label="Évolution des notes" :format="(v) => formatScore(v, 1)" /></div>
      </section>

      <div class="grid gap-4 md:grid-cols-2">
        <section class="card">
          <div class="flex items-center justify-between px-5 pt-5 pb-2"><h2 class="card-title">Dernières notes</h2><RouterLink to="/parent/grades" class="text-sm link">Tout voir</RouterLink></div>
          <p v-if="!child.grades.length" class="px-5 pb-5 text-sm text-subtle">Aucune note publiée pour le moment.</p>
          <ul v-else class="divide-y divide-line-soft">
            <li v-for="g in child.grades.slice(0, 4)" :key="g.id" class="flex items-center gap-3 px-5 py-3">
              <span class="min-w-0 flex-1"><span class="block truncate text-sm font-medium">{{ g.subject }}</span><span class="text-xs text-subtle">{{ g.title }} · {{ formatDate(g.date) }}</span></span>
              <span class="font-semibold tabular">{{ g.score == null ? 'Abs.' : formatScore(g.score) }}<span class="text-xs text-subtle">/{{ g.max }}</span></span>
            </li>
          </ul>
        </section>
        <section class="card">
          <div class="flex items-center justify-between px-5 pt-5 pb-2"><h2 class="card-title">À faire</h2><RouterLink to="/parent/homework" class="text-sm link">Devoirs</RouterLink></div>
          <p v-if="!todo(child).length" class="px-5 pb-5 text-sm text-subtle">Aucun devoir en attente.</p>
          <ul v-else class="divide-y divide-line-soft">
            <li v-for="h in todo(child)" :key="h.id" class="px-5 py-3"><p class="text-sm font-medium">{{ h.subject }} · {{ h.title }}</p><p class="text-xs text-subtle">Pour le {{ formatDate(h.due) }}</p></li>
          </ul>
        </section>
      </div>

      <section class="card">
        <div class="flex items-center justify-between px-5 pt-5 pb-2"><h2 class="card-title">Annonces de l’école</h2><RouterLink to="/parent/announcements" class="text-sm link">Toutes</RouterLink></div>
        <p v-if="!news.data.value.length" class="px-5 pb-5 text-sm text-subtle">Aucune annonce.</p>
        <ul v-else class="divide-y divide-line-soft">
          <li v-for="a in news.data.value.slice(0, 3)" :key="a.id" class="flex gap-3 px-5 py-3">
            <AppIcon name="megaphone" class="mt-0.5 size-4 shrink-0 text-brand-600" />
            <span class="min-w-0"><span class="block text-sm font-medium">{{ a.title }}</span><span class="line-clamp-2 text-xs text-muted">{{ a.body }}</span></span>
          </li>
        </ul>
      </section>
    </template>
  </ParentSection>
</template>
