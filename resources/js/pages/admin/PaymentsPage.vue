<script setup>
/** Échéances et encaissements, avec synthèse de l'année. */
import { watch } from 'vue';
import { useRoute } from 'vue-router';
import ResourceIndex from '@/pages/generic/ResourceIndex.vue';
import StatsCard from '@/components/ui/StatsCard.vue';
import { paymentsApi } from '@/services/api';
import { useAsync } from '@/composables/useAsync';
import { useContextStore } from '@/stores/context';
import { formatMoney } from '@/utils/format';

const ctx = useContextStore();
const route = useRoute();
const { data: s, loading, run } = useAsync(() => paymentsApi.summary({ school_id: ctx.schoolId || undefined }), { immediate: true });
watch(() => ctx.schoolId, () => run());
</script>

<template>
  <ResourceIndex :key="route.fullPath" resource="payments" base="/admin/payments">
    <template #before>
      <div class="mb-5 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <StatsCard label="Attendu sur l’année" :loading="loading" :value="formatMoney(s?.expected)" />
        <StatsCard label="Encaissé" :loading="loading" :value="formatMoney(s?.collected)" :hint="s?.expected ? `${Math.round((s.collected / s.expected) * 100)} % de l’attendu` : ''" hint-tone="positive" />
        <StatsCard label="En retard" :loading="loading" :value="formatMoney(s?.late_amount)" :hint="s ? `${s.late_families} famille(s)` : ''" hint-tone="warning" />
        <StatsCard label="Paiements en ligne à confirmer" :loading="loading" :value="s?.pending_online ?? 0" />
      </div>
      <p v-if="s?.pending_online" class="mb-5 rounded-xl bg-warn-50 px-4 py-3 text-sm text-warn-700">
        {{ s.pending_online }} paiement(s) en ligne attendent votre confirmation.
        <RouterLink :to="{ path: '/admin/payments', query: { has_pending: '1' } }" class="font-semibold underline">Les afficher</RouterLink>
      </p>
    </template>
  </ResourceIndex>
</template>
