<script setup>
/** Rôles et matrice des permissions (« ressource.action »). L'administrateur a tous les droits. */
import { computed, reactive, ref } from 'vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import BaseModal from '@/components/ui/BaseModal.vue';
import LoadingState from '@/components/ui/LoadingState.vue';
import ErrorState from '@/components/ui/ErrorState.vue';
import AppIcon from '@/components/ui/AppIcon.vue';
import { rolesApi } from '@/services/api';
import { useAuthStore } from '@/stores/auth';
import { useUiStore } from '@/stores/ui';

const auth = useAuthStore();
const ui = useUiStore();

const roles = ref([]);
const catalog = ref({ data: [], labels: {} });
const loading = ref(true);
const error = ref(null);
const currentId = ref(null);
const draft = ref(new Set());
const saving = ref(false);

async function load() {
  loading.value = true;
  error.value = null;
  try {
    const [r, c] = await Promise.all([rolesApi.list(), rolesApi.permissions()]);
    roles.value = r;
    catalog.value = c;
    select(currentId.value ?? r.find((x) => x.key !== 'school_admin' && x.key !== 'parent')?.id ?? r[0]?.id);
  } catch (e) {
    error.value = e;
  } finally {
    loading.value = false;
  }
}
load();

const current = computed(() => roles.value.find((r) => r.id === currentId.value));
const locked = computed(() => current.value?.key === 'school_admin' || !auth.can('roles.update'));
const allActions = computed(() => [...new Set(catalog.value.data.flatMap((g) => g.actions))]);
const dirty = computed(() => current.value && !locked.value && [...draft.value].sort().join() !== [...(current.value.permissions || [])].sort().join());

function select(id) {
  currentId.value = id;
  draft.value = new Set(roles.value.find((r) => r.id === id)?.permissions ?? []);
}
function has(key) {
  return current.value?.key === 'school_admin' || draft.value.has(key);
}
function toggle(key) {
  if (locked.value) return;
  const next = new Set(draft.value);
  next.has(key) ? next.delete(key) : next.add(key);
  draft.value = next;
}
function toggleRow(group) {
  if (locked.value) return;
  const keys = group.actions.map((a) => `${group.key}.${a}`);
  const all = keys.every((k) => draft.value.has(k));
  const next = new Set(draft.value);
  keys.forEach((k) => (all ? next.delete(k) : next.add(k)));
  draft.value = next;
}

async function save() {
  saving.value = true;
  try {
    const updated = await rolesApi.syncPermissions(currentId.value, [...draft.value]);
    roles.value = roles.value.map((r) => (r.id === updated.id ? { ...r, ...updated } : r));
    select(updated.id);
    ui.toast('Permissions enregistrées.');
  } catch (e) {
    ui.error(e);
  } finally {
    saving.value = false;
  }
}

const createOpen = ref(false);
const newRole = reactive({ name: '', description: '' });
async function create() {
  try {
    const role = await rolesApi.create(newRole);
    roles.value.push(role);
    select(role.id);
    createOpen.value = false;
    Object.assign(newRole, { name: '', description: '' });
    ui.toast('Rôle créé.');
  } catch (e) {
    ui.error(e);
  }
}
</script>

<template>
  <div class="space-y-5">
    <PageHeader title="Rôles et permissions" subtitle="Chaque utilisateur cumule les permissions de ses rôles.">
      <template v-if="auth.can('roles.create')" #actions><button type="button" class="btn btn-primary" @click="createOpen = true"><AppIcon name="plus" class="size-4" />Nouveau rôle</button></template>
    </PageHeader>

    <LoadingState v-if="loading" variant="detail" />
    <div v-else-if="error" class="card"><ErrorState :message="error.message" @retry="load" /></div>
    <div v-else class="grid gap-5 lg:grid-cols-[260px_1fr]">
      <nav class="card h-fit p-2" aria-label="Rôles">
        <button v-for="r in roles" :key="r.id" type="button" class="flex w-full items-center justify-between gap-2 rounded-lg px-3 py-2.5 text-left" :class="r.id === currentId ? 'bg-brand-100 text-brand-700' : 'hover:bg-ground'" @click="select(r.id)">
          <span class="min-w-0"><span class="block truncate text-sm font-medium">{{ r.name }}</span><span class="block truncate text-xs text-subtle">{{ r.users_count }} utilisateur(s)</span></span>
          <AppIcon v-if="r.key === 'school_admin'" name="lock" class="size-4 shrink-0 text-subtle" />
        </button>
      </nav>

      <section v-if="current" class="card overflow-hidden">
        <div class="flex flex-wrap items-center gap-3 border-b border-line-soft px-5 py-4">
          <div class="min-w-0 flex-1"><h2 class="card-title">{{ current.name }}</h2><p class="text-sm text-muted">{{ current.description || '—' }}</p></div>
          <button v-if="!locked" type="button" class="btn btn-primary" :disabled="!dirty || saving" @click="save">Enregistrer</button>
        </div>
        <p v-if="current.key === 'school_admin'" class="bg-info-50 px-5 py-2.5 text-[13px] text-info-700">L’administrateur dispose de toutes les permissions ; ce rôle n’est pas modifiable.</p>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <caption class="sr-only">Permissions du rôle {{ current.name }}</caption>
            <thead><tr class="border-b border-line text-left text-xs text-subtle"><th class="px-5 py-2.5 font-medium">Module</th><th v-for="a in allActions" :key="a" class="px-3 py-2.5 text-center font-medium">{{ catalog.labels[a] || a }}</th></tr></thead>
            <tbody>
              <tr v-for="g in catalog.data" :key="g.key" class="border-b border-line-soft last:border-0">
                <th scope="row" class="px-5 py-2.5 text-left font-medium"><button type="button" class="hover:text-brand-700" :disabled="locked" @click="toggleRow(g)">{{ g.label }}</button></th>
                <td v-for="a in allActions" :key="a" class="px-3 py-2.5 text-center">
                  <input v-if="g.actions.includes(a)" type="checkbox" class="size-4 accent-brand-600" :checked="has(`${g.key}.${a}`)" :disabled="locked" :aria-label="`${catalog.labels[a]} — ${g.label}`" @change="toggle(`${g.key}.${a}`)" />
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>

    <BaseModal :open="createOpen" title="Nouveau rôle" @close="createOpen = false">
      <form id="role-form" class="space-y-4" @submit.prevent="create">
        <div class="flex flex-col gap-1"><label for="role-name" class="label">Nom *</label><input id="role-name" v-model="newRole.name" required class="input" placeholder="Surveillant général" /></div>
        <div class="flex flex-col gap-1"><label for="role-desc" class="label">Description</label><input id="role-desc" v-model="newRole.description" class="input" /></div>
      </form>
      <template #footer><button type="button" class="btn btn-secondary" @click="createOpen = false">Annuler</button><button type="submit" form="role-form" class="btn btn-primary" :disabled="!newRole.name">Créer</button></template>
    </BaseModal>
  </div>
</template>
