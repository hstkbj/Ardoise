<script setup>
import PageHeader from '@/components/ui/PageHeader.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import LoadingState from '@/components/ui/LoadingState.vue';
import ErrorState from '@/components/ui/ErrorState.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import AppIcon from '@/components/ui/AppIcon.vue';
import { createResource } from '@/services/resource';
import { useAsync } from '@/composables/useAsync';
import { formatMoney, formatNumber } from '@/utils/format';

const api = createResource('platform/plans');
const { data, loading, error, run } = useAsync(() => api.list().then((r) => r.data ?? r), { immediate: true, initial: [] });
</script>

<template>
  <div>
    <PageHeader title="Plans" subtitle="Offres proposées aux écoles. Un prix vide s’affiche « Sur devis » sur le site.">
      <template #actions><RouterLink to="/superadmin/plans/create" class="btn btn-primary"><AppIcon name="plus" class="size-4" />Nouveau plan</RouterLink></template>
    </PageHeader>
    <LoadingState v-if="loading" variant="cards" :rows="3" />
    <div v-else-if="error" class="card"><ErrorState :message="error.message" @retry="run" /></div>
    <div v-else-if="!data.length" class="card"><EmptyState title="Aucun plan" icon="tag" /></div>
    <div v-else class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
      <article v-for="p in data" :key="p.id" class="card flex flex-col p-5">
        <div class="flex items-start justify-between gap-3"><h2 class="text-lg font-semibold">{{ p.name }}</h2><StatusBadge :status="p.status" /></div>
        <p class="mt-1 text-sm text-muted">{{ p.description || '—' }}</p>
        <p class="mt-4 text-2xl font-semibold">{{ p.price ? formatMoney(p.price) : 'Sur devis' }}<span v-if="p.price" class="text-sm font-normal text-subtle"> / {{ p.period === 'monthly' ? 'mois' : 'an' }}</span></p>
        <dl class="mt-4 grid grid-cols-3 gap-2 text-center text-xs">
          <div class="rounded-lg bg-ground p-2"><dt class="text-subtle">Sites</dt><dd class="font-semibold">{{ p.max_schools ?? '∞' }}</dd></div>
          <div class="rounded-lg bg-ground p-2"><dt class="text-subtle">Élèves</dt><dd class="font-semibold">{{ p.max_students ? formatNumber(p.max_students) : '∞' }}</dd></div>
          <div class="rounded-lg bg-ground p-2"><dt class="text-subtle">Utilisateurs</dt><dd class="font-semibold">{{ p.max_users ?? '∞' }}</dd></div>
        </dl>
        <ul class="mt-4 flex-1 space-y-1.5 text-sm"><li v-for="f in p.features" :key="f" class="flex gap-2"><AppIcon name="check" class="mt-0.5 size-4 text-brand-600" />{{ f }}</li></ul>
        <div class="mt-5 flex items-center justify-between border-t border-line-soft pt-4 text-sm">
          <span class="text-muted">{{ p.tenants_count }} tenant(s)</span>
          <RouterLink :to="`/superadmin/plans/${p.id}/edit`" class="btn btn-secondary btn-sm"><AppIcon name="edit" class="size-4" />Modifier</RouterLink>
        </div>
      </article>
    </div>
  </div>
</template>
