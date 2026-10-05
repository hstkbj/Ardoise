<script setup>
/** Formulaire générique de création / modification, d'après config/resources.js. */
import { computed, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import PageHeader from '@/components/ui/PageHeader.vue';
import FormField from '@/components/ui/FormField.vue';
import LoadingState from '@/components/ui/LoadingState.vue';
import ErrorState from '@/components/ui/ErrorState.vue';
import BaseModal from '@/components/ui/BaseModal.vue';
import AppIcon from '@/components/ui/AppIcon.vue';
import { getResource } from '@/config/resources';
import { createResource } from '@/services/resource';
import { useFormErrors } from '@/composables/useFormErrors';
import { useAuthStore, STAFF_ROLES } from '@/stores/auth';
import { useOptionsStore } from '@/stores/options';
import { useUiStore } from '@/stores/ui';
import { today } from '@/utils/format';

const props = defineProps({
  resource: { type: String, required: true },
  base: { type: String, required: true },
});

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const ui = useUiStore();
const res = getResource(props.resource);
const api = createResource(res.endpoint);
const id = route.params.id;
const isEdit = !!id;
const isStaff = auth.isPlatform || auth.hasRole(...STAFF_ROLES);

const sections = computed(() =>
  (res.form || [])
    .filter((s) => !(isEdit && s.createOnly))
    .map((s) => ({ ...s, fields: s.fields.filter((f) => !(isEdit && f.createOnly) && !(f.staffOnly && !isStaff)) }))
    .filter((s) => s.fields.length),
);
const allFields = computed(() => sections.value.flatMap((s) => s.fields));

const values = reactive({});
function initValues(item = {}) {
  (res.form || []).flatMap((s) => s.fields).forEach((f) => {
    if (item[f.key] !== undefined && f.type !== 'file' && f.type !== 'password') values[f.key] = item[f.key];
    else if (f.type === 'checkboxes') values[f.key] = [];
    else if (f.type === 'file') values[f.key] = f.multiple ? [] : null;
    else if (f.default === 'today') values[f.key] = today();
    else if (route.query[f.key] !== undefined) values[f.key] = /^\d+$/.test(route.query[f.key]) ? Number(route.query[f.key]) : route.query[f.key];
    else values[f.key] = f.default ?? '';
  });
}

const loading = ref(isEdit);
const loadError = ref(null);
const saving = ref(false);
const item = ref(null);
const { errors, validateRequired, setServerErrors } = useFormErrors();
const created = ref(null);

async function load() {
  loading.value = true;
  loadError.value = null;
  try {
    item.value = await api.get(id);
    initValues(item.value);
  } catch (e) {
    loadError.value = e;
  } finally {
    loading.value = false;
  }
}
if (isEdit) load();
else initValues();

function payload() {
  const out = {};
  allFields.value.forEach((f) => {
    const v = values[f.key];
    if (f.type === 'file' && (!v || (Array.isArray(v) && !v.length))) return;
    out[f.key] = v === '' ? null : v;
  });
  return out;
}

async function submit() {
  if (!validateRequired(allFields.value, values, { isEdit })) {
    ui.toast('Vérifiez les champs signalés.', { tone: 'error' });
    return;
  }
  saving.value = true;
  try {
    let saved;
    if (isEdit) {
      saved = await api.update(id, payload());
      ui.toast('Modifications enregistrées.');
    } else {
      const full = await api.createFull(payload());
      saved = full.data ?? full;
      ui.toast(`${res.singular.charAt(0).toUpperCase()}${res.singular.slice(1)} créé(e).`);
      if (full.meta?.parent_access_code || full.meta?.admin_password) {
        created.value = { item: saved, meta: full.meta };
        refreshOptions();
        return;
      }
    }
    refreshOptions();
    goAfter(saved);
  } catch (e) {
    setServerErrors(e.errors);
    ui.error(e);
  } finally {
    saving.value = false;
  }
}

function refreshOptions() {
  if (!auth.isPlatform) useOptionsStore().load(true).catch(() => {});
}

function goAfter(saved) {
  if (!isEdit && res.afterCreate === 'show' && saved?.id) router.push(`${props.base}/${saved.id}`);
  else if (isEdit && res.rowActions.includes('view')) router.push(`${props.base}/${id}`);
  else router.push(props.base);
}

function copy(text) {
  navigator.clipboard?.writeText(text).then(() => ui.toast('Copié.'));
}

const title = computed(() => (isEdit ? `Modifier ${item.value?.[res.titleField] ?? res.singular}` : res.createLabel || `Nouveau ${res.singular}`));
</script>

<template>
  <div class="mx-auto max-w-4xl">
    <PageHeader :title="title" :breadcrumb="[{ label: res.title, to: base }, { label: isEdit ? 'Modification' : 'Création' }]" />

    <LoadingState v-if="loading" variant="detail" />
    <div v-else-if="loadError" class="card"><ErrorState :message="loadError.message" @retry="load" /></div>

    <form v-else class="space-y-5" novalidate @submit.prevent="submit">
      <section v-for="s in sections" :key="s.title" class="card p-5 sm:p-6">
        <h2 class="card-title">{{ s.title }}</h2>
        <p v-if="s.description" class="mt-1 text-sm text-muted">{{ s.description }}</p>
        <div class="mt-5 grid gap-4 sm:grid-cols-2">
          <FormField v-for="f in s.fields" :key="f.key" v-model="values[f.key]" :field="f" :error="errors[f.key]" />
        </div>
      </section>
      <div class="sticky bottom-0 -mx-1 flex justify-end gap-2 border-t border-line bg-ground/95 px-1 py-4 backdrop-blur">
        <button type="button" class="btn btn-secondary" @click="router.back()">Annuler</button>
        <button type="submit" class="btn btn-primary" :disabled="saving">{{ saving ? 'Enregistrement…' : isEdit ? 'Enregistrer' : 'Créer' }}</button>
      </div>
    </form>

    <BaseModal :open="!!created" :title="created?.meta?.admin_password ? 'Tenant créé' : 'Code d’accès du parent'" @close="goAfter(created.item); created = null">
      <template v-if="created?.meta?.parent_access_code">
        <p class="text-sm text-muted">Un compte parent a été créé. Remettez ce code au parent : il lui suffit pour se connecter à l’application.</p>
        <div class="mt-4 flex items-center justify-between gap-3 rounded-xl bg-mint px-4 py-4">
          <code class="font-mono text-xl font-semibold tracking-[0.15em]">{{ created.meta.parent_access_code }}</code>
          <button type="button" class="btn btn-secondary btn-sm" @click="copy(created.meta.parent_access_code)"><AppIcon name="copy" class="size-4" />Copier</button>
        </div>
        <p class="mt-3 text-xs text-subtle">Le code reste consultable et imprimable depuis la fiche du parent.</p>
      </template>
      <template v-else-if="created?.meta?.admin_password">
        <p class="text-sm text-muted">La base de données de l’école est prête. Transmettez ces identifiants à l’administrateur ; il pourra changer son mot de passe.</p>
        <dl class="mt-4 space-y-2 rounded-xl bg-ground p-4 text-sm">
          <div class="flex justify-between gap-3"><dt class="text-muted">Adresse</dt><dd class="truncate font-medium">{{ created.meta.login_url }}</dd></div>
          <div class="flex justify-between gap-3"><dt class="text-muted">Identifiant</dt><dd class="font-medium">{{ created.item.admin_email }}</dd></div>
          <div class="flex items-center justify-between gap-3"><dt class="text-muted">Mot de passe</dt><dd class="flex items-center gap-2"><code class="font-mono font-semibold">{{ created.meta.admin_password }}</code><button type="button" class="btn btn-ghost btn-sm" aria-label="Copier le mot de passe" @click="copy(created.meta.admin_password)"><AppIcon name="copy" class="size-4" /></button></dd></div>
        </dl>
        <p class="mt-3 text-xs text-warn-700">Ce mot de passe ne sera plus affiché.</p>
      </template>
      <template #footer><button type="button" class="btn btn-primary" @click="goAfter(created.item); created = null">Continuer</button></template>
    </BaseModal>
  </div>
</template>
