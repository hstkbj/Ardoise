<script setup>
import StatusBadge from '@/components/ui/StatusBadge.vue';
import { formatDate, formatMoney } from '@/utils/format';

defineProps({ subscription: { type: Object, required: true } });
</script>

<template>
  <div class="card p-5">
    <div class="flex items-start justify-between gap-3">
      <div><p class="text-xs text-subtle">Abonnement</p><p class="text-lg font-semibold">{{ subscription.plan || '—' }}</p></div>
      <StatusBadge :status="subscription.status" />
    </div>
    <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
      <div><dt class="text-xs text-subtle">Début</dt><dd>{{ formatDate(subscription.starts_at) }}</dd></div>
      <div><dt class="text-xs text-subtle">Expiration</dt><dd>{{ formatDate(subscription.expires_at) }}</dd></div>
      <div><dt class="text-xs text-subtle">Montant</dt><dd class="tabular">{{ subscription.amount != null ? formatMoney(subscription.amount) : 'À définir' }}</dd></div>
      <div><dt class="text-xs text-subtle">Tenant</dt><dd>{{ subscription.tenant_name }}</dd></div>
    </dl>
  </div>
</template>
