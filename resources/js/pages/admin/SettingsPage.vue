<script setup>
/** Paramètres de l'école, par section (GET/PUT /settings/{section}). */
import { computed, reactive, ref, watch } from 'vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import TabNav from '@/components/ui/TabNav.vue';
import FormField from '@/components/ui/FormField.vue';
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
    { key: 'channels', label: 'Canaux actifs', type: 'checkboxes', span: 2, optionsKey: 'channels' },
    { key: 'absence_notify', label: 'Prévenir les parents d’une absence', type: 'select', options: [{ value: 'immediate', label: 'Immédiatement' }, { value: 'daily', label: 'Récapitulatif quotidien' }, { value: 'never', label: 'Jamais' }] },
    { key: 'sms_sender', label: 'Expéditeur SMS', hint: '11 caractères maximum' },
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
      <fieldset :disabled="readOnly" class="grid gap-4 sm:grid-cols-2">
        <FormField v-for="f in sections[tab].fields" :key="f.key" v-model="values[f.key]" :field="f" :error="errors[f.key]" />
      </fieldset>
      <div v-if="!readOnly" class="flex justify-end"><button type="submit" class="btn btn-primary" :disabled="saving">{{ saving ? 'Enregistrement…' : 'Enregistrer' }}</button></div>
    </form>
  </div>
</template>
