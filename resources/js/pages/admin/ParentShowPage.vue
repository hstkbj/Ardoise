<script setup>
/** Fiche parent : code d'accès (affichage, impression, régénération), coordonnées, enfants. */
import { ref } from 'vue';
import { useRoute } from 'vue-router';
import PageHeader from '@/components/ui/PageHeader.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import LoadingState from '@/components/ui/LoadingState.vue';
import ErrorState from '@/components/ui/ErrorState.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import AppIcon from '@/components/ui/AppIcon.vue';
import ParentCard from '@/components/domain/ParentCard.vue';
import ParentAccessCard from '@/components/domain/ParentAccessCard.vue';
import StudentCard from '@/components/domain/StudentCard.vue';
import { createResource } from '@/services/resource';
import { useAuthStore } from '@/stores/auth';
import { useUiStore } from '@/stores/ui';

const route = useRoute();
const auth = useAuthStore();
const ui = useUiStore();
const api = createResource('parents');
const students = createResource('students');
const id = route.params.id;

const parent = ref(null);
const children = ref([]);
const loading = ref(true);
const error = ref(null);

async function load() {
  loading.value = true;
  error.value = null;
  try {
    parent.value = await api.get(id);
    children.value = await Promise.all((parent.value.student_ids ?? []).map((sid) => students.get(sid).catch(() => null))).then((l) => l.filter(Boolean));
  } catch (e) {
    error.value = e;
  } finally {
    loading.value = false;
  }
}
load();

function onCodeUpdated(updated) {
  parent.value = { ...parent.value, ...updated };
}

async function toggle() {
  const disabling = parent.value.status !== 'inactive';
  if (disabling && !(await ui.confirm({ title: 'Désactiver l’accès ?', message: 'Le parent sera déconnecté et son code ne fonctionnera plus jusqu’à réactivation.', confirmLabel: 'Désactiver', danger: true }))) return;
  try {
    const out = await api.action(id, 'toggle');
    ui.toast(out?.message || 'Accès mis à jour.');
    load();
  } catch (e) {
    ui.error(e);
  }
}
</script>

<template>
  <div>
    <LoadingState v-if="loading" variant="detail" />
    <div v-else-if="error" class="card"><ErrorState :message="error.message" @retry="load" /></div>
    <template v-else>
      <PageHeader :title="parent.full_name" :breadcrumb="[{ label: 'Parents', to: '/admin/parents' }, { label: parent.full_name }]">
        <template #subtitle><StatusBadge :status="parent.status" /></template>
        <template v-if="auth.can('parents.update')" #actions>
          <button type="button" class="btn btn-secondary" @click="toggle"><AppIcon :name="parent.status === 'inactive' ? 'unlock' : 'lock'" class="size-4" />{{ parent.status === 'inactive' ? 'Réactiver l’accès' : 'Désactiver l’accès' }}</button>
          <RouterLink :to="`/admin/parents/${id}/edit`" class="btn btn-primary"><AppIcon name="edit" class="size-4" />Modifier</RouterLink>
        </template>
      </PageHeader>

      <div class="grid gap-5 lg:grid-cols-[1fr_1.3fr]">
        <div class="space-y-5">
          <ParentAccessCard :parent="parent" @updated="onCodeUpdated" />
          <ParentCard :parent="parent" />
        </div>
        <section class="card p-5">
          <h2 class="card-title">Enfants ({{ children.length }})</h2>
          <div v-if="children.length" class="mt-4 grid gap-3">
            <StudentCard v-for="c in children" :key="c.id" :student="c" :to="`/admin/students/${c.id}`" />
          </div>
          <EmptyState v-else compact title="Aucun enfant associé" description="Associez des élèves depuis le formulaire de modification." icon="users" />
        </section>
      </div>
    </template>
  </div>
</template>
