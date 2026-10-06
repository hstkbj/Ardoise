<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import ParentSection from '@/components/domain/ParentSection.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import BaseModal from '@/components/ui/BaseModal.vue';
import AppIcon from '@/components/ui/AppIcon.vue';
import { parentApi, paymentsApi } from '@/services/api';
import { useAuthStore } from '@/stores/auth';
import { useUiStore } from '@/stores/ui';
import { useParentChildren } from '@/composables/useParentChildren';
import { formatDate, formatMoney, openFile } from '@/utils/format';

const ui = useUiStore();
const auth = useAuthStore();
const route = useRoute();
const router = useRouter();
const { reload: reloadChildren } = useParentChildren();
/** L'école a activé FedaPay : le parent est redirigé vers la page de paiement sécurisée. */
const online = computed(() => !!auth.user?.online_payment);
const target = ref(null);
const form = reactive({ method: 'Mobile money', phone: '', amount: '' });
const result = ref(null);
const sending = ref(false);
let reloadFn = null;

const remaining = (child) => child.payments.reduce((a, p) => a + Number(p.remaining), 0);

function pay(p, reload) {
  target.value = p;
  result.value = null;
  form.amount = p.remaining;
  reloadFn = reload;
}

async function submit() {
  sending.value = true;
  try {
    const out = await parentApi.checkout(target.value.id, { method: online.value ? 'FedaPay' : form.method, phone: form.phone || null, amount: Number(form.amount) || null });
    if (out.redirect_url) {
      window.location.assign(out.redirect_url);
      return;
    }
    result.value = out;
    reloadFn?.();
  } catch (e) {
    ui.error(e);
  } finally {
    sending.value = false;
  }
}

/** Retour de FedaPay : /parent/payments?payment=REC-… */
onMounted(async () => {
  const reference = route.query.payment;
  if (!reference) return;
  router.replace({ query: {} });
  try {
    const out = await parentApi.verifyPayment(String(reference));
    if (out.status === 'paid') ui.toast('Paiement confirmé. Merci ! Votre reçu est disponible.');
    else if (out.status === 'failed') ui.toast('Le paiement n’a pas abouti. Vous pouvez réessayer.', { tone: 'error' });
    else ui.toast('Paiement en cours de confirmation par FedaPay.', { tone: 'info' });
    reloadChildren();
  } catch (e) {
    ui.error(e);
  }
});
</script>

<template>
  <ParentSection title="Paiements">
    <template #default="{ child, reload }">
      <div class="rounded-2xl bg-sun-soft p-5">
        <p class="text-sm text-muted">Reste à payer pour {{ child.first_name }}</p>
        <p class="mt-1 text-3xl font-semibold tabular">{{ formatMoney(remaining(child)) }}</p>
      </div>
      <div class="card">
        <p v-if="!child.payments.length" class="p-6 text-center text-sm text-subtle">Aucun frais pour cette année.</p>
        <ul v-else class="divide-y divide-line-soft">
          <li v-for="p in child.payments" :key="p.id" class="flex flex-wrap items-center gap-3 px-5 py-4">
            <span class="min-w-0 flex-1">
              <span class="block text-sm font-medium">{{ p.fee_name }}</span>
              <span class="text-xs text-subtle">{{ formatMoney(p.amount) }} · échéance {{ formatDate(p.due_date) }}<template v-if="p.paid_amount > 0 && p.status !== 'paid'"> · déjà payé {{ formatMoney(p.paid_amount) }}</template></span>
            </span>
            <StatusBadge :status="p.status" />
            <button v-if="p.status !== 'paid'" type="button" class="btn btn-primary btn-sm" @click="pay(p, reload)">Payer</button>
            <button v-if="p.paid_amount > 0" type="button" class="btn btn-ghost btn-sm" @click="openFile(paymentsApi.receiptPath(p.id))"><AppIcon name="download" class="size-4" />Reçu</button>
          </li>
        </ul>
      </div>
    </template>
  </ParentSection>

  <BaseModal :open="!!target" title="Payer une échéance" :description="target?.fee_name" @close="target = null">
    <div v-if="result" class="space-y-3" role="status">
      <p class="rounded-xl bg-mint p-4 text-sm">{{ result.message }}</p>
      <p class="text-xs text-subtle">Référence : {{ result.reference }}</p>
    </div>
    <form v-else id="pay-form" class="space-y-4" @submit.prevent="submit">
      <p v-if="online" class="rounded-xl bg-mint p-3 text-[13px]">Paiement sécurisé par FedaPay : Mobile Money ou carte bancaire. Vous serez redirigé, puis ramené ici.</p>
      <div v-else class="flex flex-col gap-1"><label for="pay-method" class="label">Moyen de paiement</label><select id="pay-method" v-model="form.method" class="input"><option>Mobile money</option><option>Virement</option><option>Carte</option></select></div>
      <div v-if="online || form.method === 'Mobile money'" class="flex flex-col gap-1"><label for="pay-phone" class="label">Numéro mobile money</label><input id="pay-phone" v-model="form.phone" type="tel" class="input" autocomplete="tel" /></div>
      <div class="flex flex-col gap-1"><label for="pay-amount" class="label">Montant (FCFA)</label><input id="pay-amount" v-model="form.amount" type="number" min="1" :max="target?.remaining" class="input" /></div>
    </form>
    <template #footer>
      <button type="button" class="btn btn-secondary" @click="target = null">{{ result ? 'Fermer' : 'Annuler' }}</button>
      <button v-if="!result" type="submit" form="pay-form" class="btn btn-primary" :disabled="sending">{{ online ? 'Payer avec FedaPay' : 'Continuer' }}</button>
    </template>
  </BaseModal>
</template>
