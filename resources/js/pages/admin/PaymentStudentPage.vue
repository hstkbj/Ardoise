<script setup>
/** Frais d'un élève : ce qu'il doit, ce qu'il a payé, ce qui lui reste, échéance par échéance. */
import { ref } from 'vue';
import { useRoute } from 'vue-router';
import PageHeader from '@/components/ui/PageHeader.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import StatsCard from '@/components/ui/StatsCard.vue';
import LoadingState from '@/components/ui/LoadingState.vue';
import ErrorState from '@/components/ui/ErrorState.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import AppIcon from '@/components/ui/AppIcon.vue';
import { paymentsApi } from '@/services/api';
import { useAuthStore } from '@/stores/auth';
import { formatDate, formatMoney, openFile } from '@/utils/format';

const route = useRoute();
const auth = useAuthStore();
const id = route.params.id;
const data = ref(null);
const loading = ref(true);
const error = ref(null);

async function load() {
  loading.value = true;
  error.value = null;
  try {
    data.value = await paymentsApi.student(id);
  } catch (e) {
    error.value = e;
  } finally {
    loading.value = false;
  }
}
load();
</script>

<template>
  <div class="mx-auto max-w-5xl space-y-5">
    <LoadingState v-if="loading" variant="detail" />
    <div v-else-if="error" class="card"><ErrorState :message="error.message" @retry="load" /></div>
    <template v-else>
      <PageHeader :title="data.student.full_name" :breadcrumb="[{ label: 'Paiements', to: { path: '/admin/payments', query: { class_id: data.student.class_id || undefined } } }, { label: data.student.full_name }]">
        <template #subtitle>
          <span class="inline-flex flex-wrap items-center gap-2">{{ data.student.matricule }} · {{ data.student.class_name || 'Sans classe' }} <StatusBadge :status="data.totals.status" /></span>
        </template>
        <template #actions>
          <RouterLink :to="`/admin/students/${id}`" class="btn btn-secondary"><AppIcon name="user" class="size-4" />Fiche de l’élève</RouterLink>
          <RouterLink v-if="auth.can('payments.create') && data.totals.remaining > 0" :to="{ path: '/admin/payments/create', query: { student_id: id } }" class="btn btn-primary"><AppIcon name="wallet" class="size-4" />Encaisser</RouterLink>
        </template>
      </PageHeader>

      <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <StatsCard label="Total à payer" :value="formatMoney(data.totals.expected)" />
        <StatsCard label="Déjà payé" :value="formatMoney(data.totals.paid)" hint-tone="positive" />
        <StatsCard label="Reste à payer" :value="formatMoney(data.totals.remaining)" :hint="data.totals.remaining ? 'Échéances non soldées' : 'À jour'" :hint-tone="data.totals.remaining ? 'warning' : 'positive'" />
        <StatsCard label="Dont en retard" :value="formatMoney(data.totals.overdue_amount)" :hint-tone="data.totals.overdue_amount ? 'warning' : 'positive'" />
      </div>

      <div class="card">
        <div class="px-5 pt-5 pb-3"><h2 class="card-title">Frais de l’année</h2></div>
        <EmptyState v-if="!data.fees.length" title="Aucun frais" description="Aucun frais n’est assigné à cet élève pour l’année en cours." icon="wallet" compact />
        <div v-else class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="text-left text-subtle">
                <th class="px-5 py-2">Frais</th>
                <th class="px-3 py-2">Échéance</th>
                <th class="px-3 py-2 text-right">Montant</th>
                <th class="px-3 py-2 text-right">Payé</th>
                <th class="px-3 py-2 text-right">Reste</th>
                <th class="px-3 py-2">Statut</th>
                <th class="px-5 py-2 text-right"><span class="sr-only">Actions</span></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-line-soft">
              <tr v-for="f in data.fees" :key="f.id">
                <td class="px-5 py-3 font-medium">{{ f.fee_name }}</td>
                <td class="px-3 py-3">{{ formatDate(f.due_date) }}</td>
                <td class="px-3 py-3 text-right tabular">{{ formatMoney(f.amount) }}</td>
                <td class="px-3 py-3 text-right tabular">{{ formatMoney(f.paid_amount) }}</td>
                <td class="px-3 py-3 text-right font-semibold tabular">{{ formatMoney(f.remaining) }}</td>
                <td class="px-3 py-3"><StatusBadge :status="f.status" /></td>
                <td class="px-5 py-3 text-right whitespace-nowrap">
                  <button v-if="f.paid_amount > 0" type="button" class="btn btn-ghost btn-sm" @click="openFile(paymentsApi.receiptPath(f.id))"><AppIcon name="printer" class="size-4" />Reçu</button>
                  <RouterLink :to="`/admin/payments/${f.id}`" class="btn btn-ghost btn-sm">Détail</RouterLink>
                  <RouterLink v-if="auth.can('payments.create') && f.remaining > 0" :to="{ path: '/admin/payments/create', query: { student_id: id, fee_id: f.fee_id } }" class="btn btn-secondary btn-sm">Encaisser</RouterLink>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div v-if="data.fees.some((f) => f.payments?.length)" class="card">
        <div class="px-5 pt-5 pb-3"><h2 class="card-title">Versements</h2></div>
        <ul class="divide-y divide-line-soft">
          <template v-for="f in data.fees" :key="f.id">
            <li v-for="p in f.payments" :key="p.id" class="flex flex-wrap items-center justify-between gap-3 px-5 py-3 text-sm">
              <div class="min-w-0">
                <p class="font-medium">{{ p.reference }} · {{ f.fee_name }}</p>
                <p class="text-xs text-muted">{{ formatDate(p.paid_at) }} · {{ p.method }}<span v-if="p.received_by"> · reçu par {{ p.received_by }}</span></p>
              </div>
              <div class="flex items-center gap-3"><span class="font-semibold tabular">{{ formatMoney(p.amount) }}</span><StatusBadge :status="p.status" /></div>
            </li>
          </template>
        </ul>
      </div>
    </template>
  </div>
</template>
