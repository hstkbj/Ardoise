<script setup>
/** Tableau compact des paiements (tableau de bord, profil élève). */
import DataTable from '@/components/ui/DataTable.vue';

const props = defineProps({
  payments: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  showStudent: { type: Boolean, default: true },
});

const columns = [
  { key: 'reference', label: 'Reçu' },
  { key: 'student_name', label: 'Élève', type: 'person', sub: 'class_name' },
  { key: 'fee_name', label: 'Motif' },
  { key: 'amount', label: 'Montant', type: 'money', align: 'right' },
  { key: 'paid_amount', label: 'Payé', type: 'money', align: 'right' },
  { key: 'method', label: 'Méthode' },
  { key: 'paid_at', label: 'Date', type: 'date' },
  { key: 'status', label: 'Statut', type: 'status' },
];
</script>

<template>
  <DataTable :columns="showStudent ? columns : columns.filter((c) => c.key !== 'student_name')" :rows="props.payments" :loading="loading" empty-title="Aucun paiement" empty-text="Les paiements enregistrés apparaîtront ici." caption="Paiements" />
</template>
