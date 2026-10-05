<script setup>
import StatusBadge from '@/components/ui/StatusBadge.vue';
import { formatNumber } from '@/utils/format';

defineProps({ school: { type: Object, required: true }, to: { type: [String, Object], default: null } });
</script>

<template>
  <component :is="to ? 'RouterLink' : 'div'" :to="to" class="card block p-5" :class="to ? 'hover:border-brand-300' : ''">
    <div class="flex items-start justify-between gap-3">
      <div class="flex items-center gap-3">
        <span class="flex size-10 items-center justify-center rounded-lg bg-ink text-xs font-bold text-white">{{ school.code?.slice(0, 3) }}</span>
        <div><p class="font-semibold">{{ school.name }}</p><p class="text-sm text-muted">{{ school.city }}</p></div>
      </div>
      <StatusBadge :status="school.status" />
    </div>
    <dl class="mt-5 grid grid-cols-3 gap-3 border-t border-line-soft pt-4 text-sm">
      <div><dt class="text-xs text-subtle">Élèves</dt><dd class="font-semibold tabular">{{ formatNumber(school.students_count) }}</dd></div>
      <div><dt class="text-xs text-subtle">Classes</dt><dd class="font-semibold tabular">{{ school.classes_count }}</dd></div>
      <div><dt class="text-xs text-subtle">Enseignants</dt><dd class="font-semibold tabular">{{ school.teachers_count }}</dd></div>
    </dl>
  </component>
</template>
