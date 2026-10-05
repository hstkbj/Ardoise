<script setup>
import ParentSection from '@/components/domain/ParentSection.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import AppIcon from '@/components/ui/AppIcon.vue';
import { formatDate } from '@/utils/format';
</script>

<template>
  <ParentSection title="Bulletins">
    <template #default="{ child }">
      <div class="card">
        <p v-if="!child.report_cards.length" class="p-6 text-center text-sm text-subtle">Aucun bulletin pour le moment.</p>
        <ul v-else class="divide-y divide-line-soft">
          <li v-for="r in child.report_cards" :key="r.term_id">
            <RouterLink v-if="r.status === 'published'" :to="{ path: `/parent/report-cards/${child.id}`, query: { term_id: r.term_id } }" class="flex items-center gap-3 px-5 py-4 hover:bg-ground-soft">
              <AppIcon name="file" class="size-5 text-brand-600" />
              <span class="min-w-0 flex-1"><span class="block font-medium">{{ r.term }}</span><span class="text-xs text-subtle">Publié le {{ formatDate(r.published_at) }}</span></span>
              <AppIcon name="chevron-right" class="size-4 text-subtle" />
            </RouterLink>
            <div v-else class="flex items-center gap-3 px-5 py-4 text-muted">
              <AppIcon name="clock" class="size-5" />
              <span class="flex-1">{{ r.term }}</span>
              <StatusBadge status="upcoming" label="Pas encore publié" />
            </div>
          </li>
        </ul>
      </div>
    </template>
  </ParentSection>
</template>
