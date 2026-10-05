<script setup>
/** Détail d'une échéance : versements, reçus, confirmation des paiements en ligne. */
import { ref } from 'vue';
import { useRoute } from 'vue-router';
import PageHeader from '@/components/ui/PageHeader.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import StatsCard from '@/components/ui/StatsCard.vue';
import LoadingState from '@/components/ui/LoadingState.vue';
import ErrorState from '@/components/ui/ErrorState.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import AppIcon from '@/components/ui/AppIcon.vue';
import { createResource } from '@/services/resource';
import { paymentsApi } from '@/services/api';
import { useAuthStore } from '@/stores/auth';
import { useUiStore } from '@/stores/ui';
import { formatDate, formatMoney, openFile } from '@/utils/format';

const route = useRoute();
const auth = useAuthStore();
const ui = useUiStore();
const id = route.params.id;
const line = ref(null);
const loading = ref(true);
const error = ref(null);

async function load() {
  loading.value = true;
  error.value = null;
  try {
    line.value = await createResource('payments').get(id);
  } catch (e) {
    error.value = e;
  } finally {
    loading.value = false;
  }
}
load();

async function act(record, action) {
  const ok = await ui.confirm(action === 'confirm'
    ? { title: 'Confirmer le paiement ?', message: `${formatMoney(record.amount)} reçu (${record.method}${record.transaction_ref ? ` · ${record.transaction_ref}` : ''}).`, confirmLabel: 'Confirmer' }
    : { title: 'Annuler ce paiement ?', message: 'Le montant sera retiré de l’échéance.', confirmLabel: 'Annuler le paiement', danger: true });
  if (!ok) return;
  try {
    const out = await paymentsApi[action](record.id);
    ui.toast(out?.message || 'Paiement mis à jour.');
    load();
  } catch (e) {
    ui.error(e);
  }
}
</script>

<template>
  <div class="mx-auto max-w-4xl">
    <LoadingState v-if="loading" variant="detail" />
    <div v-else-if="error" class="card"><ErrorState :message="error.message" @retry="load" /></div>
    <template v-else>
      <PageHeader :title="line.fee_name" :breadcrumb="[{ label: 'Paiements', to: '/admin/payments' }, { label: line.student_name }]">
        <template #subtitle><span class="inline-flex items-center gap-2"><RouterLink :to="`/admin/students/${line.student_id}`" class="link">{{ line.student_name }}</RouterLink> · {{ line.class_name }} · échéance du {{ formatDate(line.due_date) }} <StatusBadge :status="line.status" /></span></template>
        <template v-if="auth.can('payments.create') && line.remaining > 0" #actions>
          <RouterLink :to="{ path: '/admin/payments/create', query: { student_id: line.student_id, fee_id: line.fee_id, paid_amount: line.remaining } }" class="btn btn-primary"><AppIcon name="wallet" class="size-4" />Encaisser</RouterLink>
        </template>
      </PageHeader>

      <div class="mb-5 grid grid-cols-3 gap-4">
        <StatsCard label="Montant" :value="formatMoney(line.amount)" />
        <StatsCard label="Payé" :value="formatMoney(line.paid_amount)" />
        <StatsCard label="Reste" :value="formatMoney(line.remaining)" :hint-tone="line.remaining ? 'warning' : 'positive'" :hint="line.remaining ? '' : 'Soldé'" />
      </div>

      <section class="card">
        <h2 class="card-title px-5 pt-5 pb-3">Versements</h2>
        <EmptyState v-if="!line.payments?.length" compact title="Aucun versement" icon="wallet" />
        <ul v-else class="divide-y divide-line-soft">
          <li v-for="p in line.payments" :key="p.id" class="flex flex-wrap items-center gap-3 px-5 py-3.5">
            <div class="min-w-0 flex-1">
              <p class="font-medium tabular">{{ formatMoney(p.amount) }} <span class="text-sm font-normal text-muted">· {{ p.method }}</span></p>
              <p class="text-xs text-subtle">{{ p.reference }} · {{ formatDate(p.paid_at) }}<template v-if="p.transaction_ref"> · réf. {{ p.transaction_ref }}</template><template v-if="p.received_by"> · reçu par {{ p.received_by }}</template></p>
            </div>
            <StatusBadge :status="p.status" />
            <template v-if="p.status === 'pending' && auth.can('payments.update')">
              <button type="button" class="btn btn-primary btn-sm" @click="act(p, 'confirm')">Confirmer</button>
              <button type="button" class="btn btn-secondary btn-sm" @click="act(p, 'cancel')">Refuser</button>
            </template>
            <template v-else-if="p.status === 'paid'">
              <button type="button" class="btn btn-ghost btn-sm" @click="openFile(paymentsApi.receiptPath(line.id, p.id))"><AppIcon name="printer" class="size-4" />Reçu</button>
              <button v-if="auth.can('payments.delete')" type="button" class="btn btn-ghost btn-sm text-danger-600" @click="act(p, 'cancel')">Annuler</button>
            </template>
          </li>
        </ul>
      </section>
    </template>
  </div>
</template>
