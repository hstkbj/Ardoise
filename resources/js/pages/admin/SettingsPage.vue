<script setup>
/** Paramètres de l'école, par section (GET/PUT /settings/{section}). */
import { computed, reactive, ref, watch } from 'vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import TabNav from '@/components/ui/TabNav.vue';
import FormField from '@/components/ui/FormField.vue';
import AppIcon from '@/components/ui/AppIcon.vue';
import LoadingState from '@/components/ui/LoadingState.vue';
import ErrorState from '@/components/ui/ErrorState.vue';
import { settingsApi } from '@/services/api';
import { useAuthStore } from '@/stores/auth';
import { useUiStore } from '@/stores/ui';

const props = defineProps({ platform: { type: Boolean, default: false } });
const auth = useAuthStore();
const ui = useUiStore();

const yesNo = [{ value: 'yes', label: 'Oui' }, { value: 'no', label: 'Non' }];
const opts = (list) => list.map((v) => ({ value: v, label: v }));

const SCHOOL = {
  identity: { label: 'Identité', fields: [
    { key: 'name', label: 'Nom de l’école', required: true, span: 2 },
    { key: 'motto', label: 'Devise', span: 2 },
    { key: 'primary_color', label: 'Couleur principale', type: 'color' },
    { key: 'logo', label: 'Logo', type: 'file', accept: 'image/*', hint: 'PNG ou JPG, 2 Mo max.' },
  ] },
  contact: { label: 'Coordonnées', fields: [
    { key: 'address', label: 'Adresse', span: 2 },
    { key: 'phone', label: 'Téléphone', type: 'tel' },
    { key: 'email', label: 'E-mail', type: 'email' },
    { key: 'legal_id', label: 'N° d’autorisation' },
    { key: 'tax_id', label: 'N° contribuable' },
  ] },
  year: { label: 'Année scolaire', fields: [
    { key: 'period_type', label: 'Découpage', type: 'select', options: [{ value: 'trimester', label: 'Trimestres' }, { value: 'semester', label: 'Semestres' }] },
    { key: 'week_days', label: 'Jours de cours', type: 'checkboxes', span: 2, options: opts(['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi']) },
  ] },
  grading: { label: 'Notation', fields: [
    { key: 'max_score', label: 'Note maximale', type: 'number', required: true },
    { key: 'pass_mark', label: 'Moyenne de passage', type: 'number', required: true },
    { key: 'rounding', label: 'Arrondi des moyennes', type: 'select', required: true, options: [{ value: '0.01', label: 'Au centième' }, { value: '0.1', label: 'Au dixième' }, { value: '0.25', label: 'Au quart de point' }, { value: '0.5', label: 'Au demi-point' }] },
    { key: 'lock_after_validation', label: 'Verrouiller après validation', type: 'select', options: yesNo },
  ] },
  reports: { label: 'Bulletins', fields: [
    { key: 'show_rank', label: 'Afficher le rang', type: 'select', options: yesNo },
    { key: 'show_class_average', label: 'Afficher la moyenne de classe', type: 'select', options: yesNo },
    { key: 'decisions', label: 'Décisions proposées (une par ligne)', type: 'textarea', span: 2, rows: 6 },
  ] },
  notifications: { label: 'Notifications', fields: [
    { key: 'absence_notify', label: 'Prévenir les parents d’une absence', type: 'select', options: [{ value: 'immediate', label: 'Immédiatement' }, { value: 'daily', label: 'Récapitulatif quotidien' }, { value: 'never', label: 'Jamais' }] },
    { key: 'sms_sender', label: 'Expéditeur SMS', hint: '11 caractères maximum' },
  ] },
  notification_rules: { label: 'Destinataires', fields: [] },
  online_payments: { label: 'Paiement en ligne', fields: [
    { key: 'enabled', label: 'Paiement des frais en ligne par les parents', type: 'select', options: [{ value: 'no', label: 'Désactivé' }, { value: 'yes', label: 'Activé (FedaPay)' }] },
    { key: 'environment', label: 'Environnement FedaPay', type: 'select', options: [{ value: 'sandbox', label: 'Test (sandbox)' }, { value: 'live', label: 'Production (live)' }] },
    { key: 'public_key', label: 'Clé publique', placeholder: 'pk_live_…', span: 2 },
    { key: 'secret_key', label: 'Clé secrète', type: 'password', placeholder: 'sk_live_…', span: 2 },
    { key: 'webhook_secret', label: 'Secret du webhook', type: 'password', placeholder: 'wh_live_…', span: 2 },
  ] },
  finance: { label: 'Finances', fields: [
    { key: 'currency', label: 'Devise', type: 'select', options: [{ value: 'XOF', label: 'Franc CFA (XOF)' }, { value: 'XAF', label: 'Franc CFA (XAF)' }, { value: 'GNF', label: 'Franc guinéen (GNF)' }] },
    { key: 'receipt_prefix', label: 'Préfixe des reçus' },
    { key: 'late_after_days', label: 'Retard après (jours)', type: 'number' },
    { key: 'methods', label: 'Moyens de paiement acceptés', type: 'checkboxes', span: 2, optionsKey: 'paymentMethods' },
  ] },
};

const PLATFORM = {
  identity: { label: 'Général', fields: [
    { key: 'platform_name', label: 'Nom de la plateforme' },
    { key: 'support_email', label: 'E-mail du support', type: 'email' },
    { key: 'support_phone', label: 'Téléphone du support', type: 'tel' },
  ] },
  saas: { label: 'Abonnements', fields: [
    { key: 'trial_days', label: 'Durée d’essai (jours)', type: 'number' },
    { key: 'grace_days', label: 'Délai de grâce (jours)', type: 'number' },
    { key: 'currency', label: 'Devise', type: 'select', options: [{ value: 'XOF', label: 'XOF' }, { value: 'XAF', label: 'XAF' }] },
  ] },
  notifications: { label: 'SMS', fields: [
    { key: 'sender', label: 'Expéditeur par défaut' },
    { key: 'monthly_quota', label: 'Quota mensuel par école', type: 'number' },
  ] },
};

const sections = props.platform ? PLATFORM : SCHOOL;
const tab = ref(Object.keys(sections)[0]);
const tabs = Object.entries(sections).map(([key, s]) => ({ key, label: s.label }));
const values = reactive({});
const loading = ref(false);
const error = ref(null);
const saving = ref(false);
const errors = ref({});
const secretHint = (key) => (values._extra?.[`${key}_set`] ? 'Déjà enregistrée — laisser vide pour la conserver.' : '');
const ruleGroups = computed(() => {
  const groups = {};
  (values._extra?.catalog?.events ?? []).forEach((e) => (groups[e.group] ??= []).push(e));
  return groups;
});
function toggleRule(event, kind, value) {
  const list = values.rules[event][kind];
  const i = list.indexOf(value);
  i === -1 ? list.push(value) : list.splice(i, 1);
}
function copy(text) {
  navigator.clipboard?.writeText(text).then(() => ui.toast('Copié.'));
}

const readOnly = computed(() => !props.platform && !auth.can('settings.update'));

async function load() {
  loading.value = true;
  error.value = null;
  errors.value = {};
  try {
    const data = await (props.platform ? settingsApi.platformGet(tab.value) : settingsApi.get(tab.value));
    Object.keys(values).forEach((k) => delete values[k]);
    sections[tab.value].fields.forEach((f) => {
      const v = data?.[f.key];
      values[f.key] = f.type === 'file' ? null : f.type === 'checkboxes' ? (Array.isArray(v) ? v : []) : v ?? '';
    });
    values._logo = data?.logo ?? null;
    values._extra = data;
    if (tab.value === 'notification_rules') values.rules = JSON.parse(JSON.stringify(data.rules));
  } catch (e) {
    error.value = e;
  } finally {
    loading.value = false;
  }
}
watch(tab, load);
load();

async function save() {
  saving.value = true;
  errors.value = {};
  try {
    const payload = Object.fromEntries(Object.entries(values).filter(([k, v]) => !k.startsWith('_') && !(v === null && sections[tab.value].fields.find((f) => f.key === k)?.type === 'file')));
    await (props.platform ? settingsApi.platformSave(tab.value, payload) : settingsApi.save(tab.value, payload));
    ui.toast('Paramètres enregistrés.');
    load();
  } catch (e) {
    errors.value = Object.fromEntries(Object.entries(e.errors || {}).map(([k, v]) => [k.split('.')[0], v[0]]));
    ui.error(e);
  } finally {
    saving.value = false;
  }
}
</script>

<template>
  <div class="mx-auto max-w-4xl space-y-5">
    <PageHeader title="Paramètres" :subtitle="platform ? 'Configuration de la plateforme.' : 'Configuration de votre école.'" />
    <TabNav v-model="tab" :tabs="tabs" />
    <LoadingState v-if="loading" variant="detail" />
    <div v-else-if="error" class="card"><ErrorState :message="error.message" @retry="load" /></div>
    <form v-else class="card space-y-5 p-5 sm:p-6" novalidate @submit.prevent="save">
      <div v-if="tab === 'identity' && values._logo" class="flex items-center gap-3"><img :src="values._logo" alt="Logo actuel" class="size-14 rounded-lg border border-line object-contain" /><span class="text-sm text-muted">Logo actuel</span></div>
      <template v-if="tab === 'online_payments'">
        <p v-if="values._extra?.available === false" class="rounded-xl bg-warn-50 p-4 text-sm text-warn-700">Le paiement en ligne n’est pas inclus dans votre abonnement. <RouterLink to="/admin/billing" class="font-semibold underline">Voir les formules</RouterLink></p>
        <div class="rounded-xl bg-ground p-4 text-sm text-body">
          <p>Les parents paient les frais par Mobile Money ou carte ; l’argent arrive <b>directement sur le compte FedaPay de l’école</b>. Les clés se trouvent dans votre tableau de bord FedaPay → Paramètres → Clés API.</p>
          <p class="mt-3 text-xs text-muted">Déclarez ce webhook dans FedaPay (événements « transaction.* »), puis copiez son secret ci-dessous :</p>
          <div class="mt-1 flex items-center gap-2"><code class="min-w-0 flex-1 truncate rounded bg-white px-2 py-1.5 text-xs">{{ values._extra?.webhook_url }}</code><button type="button" class="btn btn-secondary btn-sm" @click="copy(values._extra?.webhook_url)"><AppIcon name="copy" class="size-4" />Copier</button></div>
        </div>
      </template>

      <div v-if="tab === 'notification_rules' && values.rules" class="space-y-6">
        <p class="text-sm text-muted">Choisissez qui est prévenu pour chaque événement, et par quel canal. Les notifications partent en arrière-plan. Les annonces ont leurs propres destinataires, choisis à la publication.</p>
        <p v-if="!values._extra.sms_available" class="rounded-xl bg-ground p-3 text-xs text-muted">Les SMS ne sont pas inclus dans votre abonnement : ce canal est ignoré.</p>
        <fieldset v-for="(events, group) in ruleGroups" :key="group" :disabled="readOnly" class="space-y-3">
          <legend class="text-xs font-semibold tracking-wide text-subtle uppercase">{{ group }}</legend>
          <div v-for="e in events" :key="e.key" class="rounded-xl border border-line p-4">
            <p class="text-sm font-semibold">{{ e.label }}</p>
            <div class="mt-3 flex flex-wrap gap-2" role="group" :aria-label="`Destinataires : ${e.label}`">
              <label v-for="r in values._extra.catalog.recipients" :key="r.value" class="flex cursor-pointer items-center gap-1.5 rounded-full border px-3 py-1 text-[13px]" :class="values.rules[e.key].recipients.includes(r.value) ? 'border-brand-600 bg-brand-50 text-brand-700' : 'border-line text-body'">
                <input type="checkbox" class="sr-only" :checked="values.rules[e.key].recipients.includes(r.value)" @change="toggleRule(e.key, 'recipients', r.value)" />{{ r.label }}
              </label>
            </div>
            <div class="mt-3 flex flex-wrap items-center gap-4 border-t border-line-soft pt-3 text-[13px]" role="group" :aria-label="`Canaux : ${e.label}`">
              <span class="text-xs text-subtle">Canaux</span>
              <label v-for="c in values._extra.catalog.channels" :key="c.value" class="flex items-center gap-1.5" :class="c.value === 'sms' && !values._extra.sms_available ? 'opacity-50' : ''">
                <input type="checkbox" class="size-4 accent-brand-600" :checked="values.rules[e.key].channels.includes(c.value)" @change="toggleRule(e.key, 'channels', c.value)" />{{ c.label }}
              </label>
            </div>
          </div>
        </fieldset>
      </div>

      <fieldset v-if="sections[tab].fields.length" :disabled="readOnly" class="grid gap-4 sm:grid-cols-2">
        <FormField v-for="f in sections[tab].fields" :key="f.key" v-model="values[f.key]" :field="f.type === 'password' ? { ...f, hint: secretHint(f.key) || f.hint } : f" :error="errors[f.key]" />
      </fieldset>
      <div v-if="!readOnly" class="flex justify-end"><button type="submit" class="btn btn-primary" :disabled="saving">{{ saving ? 'Enregistrement…' : 'Enregistrer' }}</button></div>
    </form>
  </div>
</template>
