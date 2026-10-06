<script setup>
/**
 * Abonnement de l'école : état, plan et modules, consommation, paiement en
 * ligne (FedaPay) et historique. Seule page accessible quand l'abonnement
 * a expiré après le délai de grâce.
 */
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import PageHeader from '@/components/ui/PageHeader.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import ProgressBar from '@/components/ui/ProgressBar.vue';
import LoadingState from '@/components/ui/LoadingState.vue';
import ErrorState from '@/components/ui/ErrorState.vue';
import AppIcon from '@/components/ui/AppIcon.vue';
import BaseModal from '@/components/ui/BaseModal.vue';
import { billingApi } from '@/services/api';
import { useAuthStore } from '@/stores/auth';
import { useUiStore } from '@/stores/ui';
import { formatDate, formatMoney, formatNumber } from '@/utils/format';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const ui = useUiStore();

const data = ref(null);
const loading = ref(true);
const error = ref(null);
const verifying = ref(false);
const returned = ref(null);

async function load() {
  loading.value = true;
  error.value = null;
  try {
    data.value = await billingApi.get();
  } catch (e) {
    error.value = e;
  } finally {
    loading.value = false;
  }
}

/** Retour de FedaPay : /admin/billing?payment=SUB-… → vérification auprès du serveur */
async function verifyReturn(reference) {
  verifying.value = true;
  try {
    const out = await billingApi.verify(reference);
    returned.value = out.payment;
    if (out.payment.status === 'paid') {
      ui.toast('Paiement confirmé : votre abonnement est prolongé.');
      await auth.refresh();
    } else if (out.payment.status === 'failed') {
      ui.toast('Le paiement n’a pas abouti.', { tone: 'error' });
    }
  } catch (e) {
    ui.error(e);
  } finally {
    verifying.value = false;
    router.replace({ query: {} });
    load();
  }
}

onMounted(() => (route.query.payment ? verifyReturn(String(route.query.payment)) : load()));

const sub = computed(() => data.value?.subscription);
const moduleLabel = (key) => data.value?.modules.find((m) => m.value === key)?.label ?? key;
const STATE = {
  trial: { label: 'Essai', tone: 'info' },
  active: { label: 'Actif', tone: 'success' },
  grace: { label: 'Expiré — délai de grâce', tone: 'warning' },
  expired: { label: 'Expiré — accès bloqué', tone: 'danger' },
  suspended: { label: 'Suspendu', tone: 'danger' },
};
const usageRows = computed(() => [
  { key: 'students', label: 'Élèves actifs' },
  { key: 'users', label: 'Comptes (hors parents)' },
  { key: 'schools', label: 'Établissements' },
].map((r) => ({ ...r, used: data.value?.usage[r.key] ?? 0, limit: sub.value?.limits[r.key] ?? null })));

// Paiement
const selected = ref(null);
const periods = ref(1);
const paying = ref(false);
const periodLabel = (p, n = 1) => (p.period === 'yearly' ? (n > 1 ? `${n} ans` : 'an') : n > 1 ? `${n} mois` : 'mois');
const total = computed(() => (selected.value ? selected.value.price * periods.value : 0));
const canPay = computed(() => auth.hasRole('school_admin'));

function choose(plan) {
  selected.value = plan;
  periods.value = plan.period === 'yearly' ? 1 : 12;
}

async function pay() {
  paying.value = true;
  try {
    const out = await billingApi.checkout({ plan_id: selected.value.id, periods: periods.value });
    window.location.assign(out.url);
  } catch (e) {
    ui.error(e);
    paying.value = false;
  }
}
</script>

<template>
  <div class="mx-auto max-w-5xl space-y-6">
    <PageHeader title="Abonnement" :subtitle="data ? `${data.school.name} · ${data.school.domain}` : ''" />

    <p v-if="verifying" class="card flex items-center gap-3 p-4 text-sm"><AppIcon name="refresh" class="size-4 animate-spin" />Vérification du paiement auprès de FedaPay…</p>
    <p v-else-if="returned && returned.status === 'pending'" class="rounded-xl bg-info-50 p-4 text-sm text-info-700">Paiement {{ returned.reference }} en cours de confirmation : la page se mettra à jour dès que FedaPay l’aura validé.</p>

    <LoadingState v-if="loading" variant="detail" />
    <div v-else-if="error" class="card"><ErrorState :message="error.message" @retry="load" /></div>
    <template v-else-if="data">
      <div v-if="sub.requires_payment" class="rounded-xl border border-danger-600/20 bg-danger-50 p-5 text-danger-600" role="alert">
        <p class="font-semibold">L’accès à l’école est suspendu.</p>
        <p class="mt-1 text-sm">L’abonnement a expiré le {{ formatDate(sub.ends_at) }} et le délai de grâce est terminé. Enseignants, personnel et parents retrouveront l’accès dès le paiement confirmé.</p>
      </div>

      <div class="grid gap-5 lg:grid-cols-3">
        <section class="card p-5 lg:col-span-2">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
              <p class="text-xs text-subtle">Formule actuelle</p>
              <p class="text-xl font-semibold">{{ sub.plan?.name || 'Contrat sur mesure' }}</p>
            </div>
            <StatusBadge :status="sub.state" :label="STATE[sub.state]?.label" :tone="STATE[sub.state]?.tone" />
          </div>
          <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-3">
            <div><dt class="text-xs text-subtle">{{ sub.is_trial ? 'Fin de l’essai' : 'Valable jusqu’au' }}</dt><dd class="font-medium">{{ sub.ends_at ? formatDate(sub.ends_at) : 'Sans limite' }}</dd></div>
            <div v-if="sub.ends_at"><dt class="text-xs text-subtle">Jours restants</dt><dd class="font-medium tabular">{{ Math.max(0, sub.days_left) }}</dd></div>
            <div v-if="['grace', 'expired'].includes(sub.state)"><dt class="text-xs text-subtle">Blocage</dt><dd class="font-medium">{{ formatDate(sub.grace_ends_at) }}</dd></div>
          </dl>
          <h2 class="mt-6 text-sm font-semibold">Modules inclus</h2>
          <ul class="mt-2 flex flex-wrap gap-2">
            <li v-for="m in data.modules" :key="m.value" class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs" :class="sub.features.includes(m.value) ? 'bg-brand-100 text-brand-700' : 'bg-ground text-subtle line-through'">
              <AppIcon :name="sub.features.includes(m.value) ? 'check' : 'x'" class="size-3.5" />{{ m.label }}
            </li>
          </ul>
        </section>

        <section class="card p-5">
          <h2 class="card-title">Consommation</h2>
          <ul class="mt-4 space-y-4">
            <li v-for="r in usageRows" :key="r.key">
              <div class="flex justify-between text-sm"><span>{{ r.label }}</span><span class="tabular font-medium" :class="r.limit && r.used >= r.limit ? 'text-danger-600' : ''">{{ formatNumber(r.used) }}<template v-if="r.limit"> / {{ formatNumber(r.limit) }}</template><template v-else> · illimité</template></span></div>
              <ProgressBar v-if="r.limit" class="mt-1.5" :value="r.used" :max="r.limit" size="sm" :label="r.label" />
            </li>
          </ul>
        </section>
      </div>

      <section>
        <h2 class="text-lg font-semibold">{{ sub.requires_payment || sub.state === 'grace' ? 'Renouveler' : 'Prolonger ou changer de formule' }}</h2>
        <p v-if="!data.online_payment" class="mt-2 rounded-xl bg-ground p-4 text-sm text-muted">Le paiement en ligne n’est pas encore activé sur la plateforme. Contactez le support pour régler votre abonnement.</p>
        <div class="mt-4 grid gap-4 md:grid-cols-3">
          <article v-for="p in data.plans" :key="p.id" class="card flex flex-col p-5" :class="p.current ? 'ring-2 ring-brand-600' : ''">
            <div class="flex items-start justify-between gap-2"><h3 class="font-semibold">{{ p.name }}</h3><span v-if="p.current" class="rounded-full bg-brand-100 px-2 py-0.5 text-[11px] font-semibold text-brand-700">Actuelle</span></div>
            <p class="mt-1 text-sm text-muted">{{ p.description }}</p>
            <p class="mt-3 text-2xl font-semibold">{{ p.price ? formatMoney(p.price) : 'Sur devis' }}<span v-if="p.price" class="text-sm font-normal text-subtle"> / {{ periodLabel(p) }}</span></p>
            <ul class="mt-3 flex-1 space-y-1 text-[13px]">
              <li v-for="f in p.features" :key="f" class="flex gap-1.5"><AppIcon name="check" class="mt-0.5 size-3.5 shrink-0 text-brand-600" />{{ moduleLabel(f) }}</li>
              <li class="pt-1 text-xs text-subtle">{{ p.limits.students ? `${formatNumber(p.limits.students)} élèves` : 'Élèves illimités' }} · {{ p.limits.users ? `${p.limits.users} comptes` : 'comptes illimités' }} · {{ p.limits.schools ? `${p.limits.schools} site(s)` : 'sites illimités' }}</li>
            </ul>
            <button v-if="p.price && canPay && data.online_payment" type="button" class="btn mt-4" :class="p.current ? 'btn-primary' : 'btn-secondary'" @click="choose(p)">{{ p.current ? 'Renouveler' : 'Choisir' }}</button>
            <RouterLink v-else-if="!p.price" to="/admin/support" class="btn btn-secondary mt-4">Nous contacter</RouterLink>
          </article>
        </div>
        <p v-if="!canPay" class="mt-3 text-sm text-muted">Seul l’administrateur de l’école peut payer l’abonnement.</p>
      </section>

      <section class="card">
        <h2 class="card-title px-5 pt-5 pb-3">Historique des paiements</h2>
        <p v-if="!data.payments.length" class="px-5 pb-5 text-sm text-subtle">Aucun paiement pour le moment.</p>
        <ul v-else class="divide-y divide-line-soft">
          <li v-for="p in data.payments" :key="p.id" class="flex flex-wrap items-center gap-3 px-5 py-3 text-sm">
            <span class="min-w-0 flex-1"><span class="block font-medium">{{ p.plan || 'Abonnement' }}<template v-if="p.months"> · {{ p.months }} mois</template></span><span class="text-xs text-subtle">{{ p.reference }} · {{ p.method }} · {{ formatDate(p.paid_at || p.created_at) }}</span></span>
            <span class="font-medium tabular">{{ formatMoney(p.amount) }}</span>
            <StatusBadge :status="p.status" />
          </li>
        </ul>
      </section>
    </template>

    <BaseModal :open="!!selected" :title="`Payer : ${selected?.name ?? ''}`" description="Paiement sécurisé par FedaPay (Mobile Money ou carte bancaire)." @close="selected = null">
      <div v-if="selected" class="space-y-4">
        <div class="flex flex-col gap-1">
          <label for="periods" class="label">Durée</label>
          <select id="periods" v-model.number="periods" class="input">
            <option v-for="n in (selected.period === 'yearly' ? [1, 2, 3] : [1, 3, 6, 12])" :key="n" :value="n">{{ periodLabel(selected, n) }}</option>
          </select>
        </div>
        <dl class="space-y-1 rounded-xl bg-ground p-4 text-sm">
          <div class="flex justify-between"><dt class="text-muted">Montant</dt><dd class="text-lg font-semibold tabular">{{ formatMoney(total) }}</dd></div>
          <div v-if="sub?.ends_at && ['trial', 'active'].includes(sub.state)" class="flex justify-between text-xs text-muted"><dt>Prolongation à partir du</dt><dd>{{ formatDate(sub.ends_at) }}</dd></div>
        </dl>
        <p class="text-xs text-subtle">Vous allez être redirigé vers FedaPay. L’abonnement est prolongé dès la confirmation du paiement, et un reçu vous est envoyé par e-mail.</p>
      </div>
      <template #footer>
        <button type="button" class="btn btn-secondary" @click="selected = null">Annuler</button>
        <button type="button" class="btn btn-primary" :disabled="paying" @click="pay"><AppIcon name="wallet" class="size-4" />{{ paying ? 'Redirection…' : 'Payer avec FedaPay' }}</button>
      </template>
    </BaseModal>
  </div>
</template>
