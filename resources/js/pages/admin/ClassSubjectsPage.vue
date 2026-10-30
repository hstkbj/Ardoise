<script setup>
/** Matières d'une classe : affectation, coefficient et enseignant (parmi ceux qui enseignent la matière). */
import { computed, ref } from 'vue';
import { useRoute } from 'vue-router';
import PageHeader from '@/components/ui/PageHeader.vue';
import LoadingState from '@/components/ui/LoadingState.vue';
import ErrorState from '@/components/ui/ErrorState.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import AppIcon from '@/components/ui/AppIcon.vue';
import { classesApi } from '@/services/api';
import { OPTIONS } from '@/config/options';
import { useAuthStore } from '@/stores/auth';
import { useUiStore } from '@/stores/ui';

const route = useRoute();
const auth = useAuthStore();
const ui = useUiStore();
const id = route.params.id;

const rows = ref([]);
const loading = ref(true);
const error = ref(null);
const saving = ref(false);
const canEdit = auth.can('classes.update');

const className = computed(() => OPTIONS.classes.find((c) => String(c.value) === String(id))?.label ?? 'la classe');
const assignedCount = computed(() => rows.value.filter((r) => r.assigned).length);

async function load() {
  loading.value = true;
  error.value = null;
  try {
    rows.value = (await classesApi.subjects(id)).map((r) => ({ ...r, teacher_id: r.teacher_id ?? '' }));
  } catch (e) {
    error.value = e;
  } finally {
    loading.value = false;
  }
}

async function save() {
  saving.value = true;
  try {
    const subjects = rows.value.filter((r) => r.assigned).map((r) => ({ subject_id: r.subject_id, teacher_id: r.teacher_id || null, coefficient: Number(r.coefficient) }));
    await classesApi.syncSubjects(id, subjects);
    ui.toast('Matières de la classe enregistrées.');
    await load();
  } catch (e) {
    ui.error(e);
  } finally {
    saving.value = false;
  }
}

load();
</script>

<template>
  <div class="space-y-5">
    <PageHeader :title="`Matières et enseignants · ${className}`" subtitle="Cochez les matières enseignées dans cette classe et choisissez l’enseignant parmi ceux de la matière." :breadcrumb="[{ label: 'Classes', to: '/admin/classes' }, { label: className }, { label: 'Matières' }]">
      <template v-if="canEdit" #actions>
        <button type="button" class="btn btn-primary" :disabled="saving || loading" @click="save"><AppIcon name="check" class="size-4" />Enregistrer ({{ assignedCount }})</button>
      </template>
    </PageHeader>

    <LoadingState v-if="loading" variant="detail" />
    <div v-else-if="error" class="card"><ErrorState :message="error.message" @retry="load" /></div>
    <div v-else-if="!rows.length" class="card"><EmptyState title="Aucune matière" description="Créez d’abord des matières, puis rattachez-les à des enseignants." icon="book" /></div>
    <div v-else class="card overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="text-left text-subtle">
            <th class="w-10 px-4 py-3"><span class="sr-only">Affectée</span></th>
            <th class="px-4 py-3">Matière</th>
            <th class="w-28 px-4 py-3">Coefficient</th>
            <th class="px-4 py-3">Enseignant</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-line-soft">
          <tr v-for="r in rows" :key="r.subject_id" :class="{ 'opacity-60': !r.assigned }">
            <td class="px-4 py-2"><input v-model="r.assigned" type="checkbox" :disabled="!canEdit" :aria-label="`Affecter ${r.name}`" /></td>
            <td class="px-4 py-2"><span class="font-medium">{{ r.name }}</span><span class="ml-2 text-xs text-subtle">{{ r.code }}</span></td>
            <td class="px-4 py-2"><input v-model="r.coefficient" type="number" min="0" max="20" step="0.5" class="input" :disabled="!canEdit || !r.assigned" :aria-label="`Coefficient de ${r.name}`" /></td>
            <td class="px-4 py-2">
              <select v-model="r.teacher_id" class="input" :disabled="!canEdit || !r.assigned" :aria-label="`Enseignant de ${r.name}`">
                <option value="">{{ r.teachers.length ? '— Aucun —' : '— Aucun enseignant pour cette matière —' }}</option>
                <option v-for="t in r.teachers" :key="t.value" :value="t.value">{{ t.label }}</option>
              </select>
            </td>
          </tr>
        </tbody>
      </table>
      <p class="px-4 py-3 text-xs text-subtle">Seuls les enseignants rattachés à la matière (fiche enseignant → « Matières enseignées ») sont proposés. L’emploi du temps et les évaluations utilisent ces affectations.</p>
    </div>
  </div>
</template>
