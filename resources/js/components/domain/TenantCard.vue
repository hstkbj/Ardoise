<script setup>
import StatusBadge from '@/components/ui/StatusBadge.vue';
import { formatDate, formatNumber } from '@/utils/format';

defineProps({ tenant: { type: Object, required: true }, to: { type: [String, Object], default: null } });
</script>

<template>
  <component :is="to ? 'RouterLink' : 'div'" :to="to" class="card block p-5" :class="to ? 'hover:border-brand-300' : ''">
    <div class="flex items-start justify-between gap-3">
      <div class="min-w-0"><p class="truncate font-semibold">{{ tenant.name }}</p><p class="truncate text-sm text-muted">{{ tenant.domain }}</p></div>
      <StatusBadge :status="tenant.status" />
    </div>
    <dl class="mt-4 grid grid-cols-3 gap-3 border-t border-line-soft pt-4 text-sm">
      <div><dt class="text-xs text-subtle">Plan</dt><dd class="font-medium">{{ tenant.plan || '—' }}</dd></div>
      <div><dt class="text-xs text-subtle">Élèves</dt><dd class="font-medium tabular">{{ formatNumber(tenant.students_count) }}</dd></div>
      <div><dt class="text-xs text-subtle">Expire</dt><dd class="font-medium">{{ formatDate(tenant.expires_at) }}</dd></div>
    </dl>
  </component>
</template>
