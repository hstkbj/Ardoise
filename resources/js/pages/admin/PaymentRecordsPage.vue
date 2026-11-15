<script setup>
/** Toutes les échéances et tous les encaissements (reçus, paiements en ligne à confirmer). */
import { computed, watch } from 'vue';
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
const subtitle = computed(() => s.value ? `${s.value.overdue_count} échéance(s) en retard · rappels automatiques aux parents à J-3 et après échéance` : 'Suivi des échéances et des encaissements');
watch(() => ctx.schoolId, () => run());
</script>

<template>
  <ResourceIndex :key="route.fullPath" resource="payments" base="/admin/payments" title="Échéances et reçus">
    <template #before>
      <div class="mb-5 grid grid-cols-2 gap-4 lg:grid-cols-3 xl:grid-cols-6">
        <StatsCard label="Attendu sur l’année" :loading="loading" :value="formatMoney(s?.expected)" />
        <StatsCard label="Encaissé" :loading="loading" :value="formatMoney(s?.collected)" :hint="s?.expected ? `${Math.round((s.collected / s.expected) * 100)} % de l’attendu` : ''" hint-tone="positive" />
        <StatsCard label="Reste total à payer" :loading="loading" :value="formatMoney(s?.remaining)" :hint="s ? `${s.overdue_count} échéance(s) en retard` : ''" hint-tone="warning" />
        <StatsCard label="Échéances à venir (30 jours)" :loading="loading" :value="formatMoney(s?.due_soon_amount)" :hint="s ? `${s.due_soon_count} échéance(s)` : ''" />
        <StatsCard label="En retard" :loading="loading" :value="formatMoney(s?.overdue_amount)" :hint="s ? `${s.overdue_count} échéance(s)` : ''" hint-tone="warning" />
        <StatsCard label="Paiements en ligne à confirmer" :loading="loading" :value="s?.pending_online ?? 0" />
      </div>
      <p class="mb-5 text-sm text-muted">{{ subtitle }} · <RouterLink to="/admin/payments" class="link">Retour aux élèves</RouterLink></p>
      <p v-if="s?.pending_online" class="mb-5 rounded-xl bg-warn-50 px-4 py-3 text-sm text-warn-700">
        {{ s.pending_online }} paiement(s) en ligne attendent votre confirmation.
        <RouterLink :to="{ path: '/admin/payments/records', query: { has_pending: '1' } }" class="font-semibold underline">Les afficher</RouterLink>
      </p>
    </template>
  </ResourceIndex>
</template>
