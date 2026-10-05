<script setup>
/** Emploi du temps par classe (ou de l'enseignant connecté). */
import { reactive, ref, watch } from 'vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import BaseModal from '@/components/ui/BaseModal.vue';
import LoadingState from '@/components/ui/LoadingState.vue';
import ErrorState from '@/components/ui/ErrorState.vue';
import AppIcon from '@/components/ui/AppIcon.vue';
import TimetableGrid from '@/components/domain/TimetableGrid.vue';
import { timetableApi } from '@/services/api';
import { OPTIONS } from '@/config/options';
import { useAuthStore } from '@/stores/auth';
import { useUiStore } from '@/stores/ui';
import { DAYS } from '@/utils/format';

const props = defineProps({ teacherView: { type: Boolean, default: false } });
const auth = useAuthStore();
const ui = useUiStore();

const classId = ref(props.teacherView ? '' : OPTIONS.classes[0]?.value ?? '');
const entries = ref([]);
const loading = ref(true);
const error = ref(null);
const canEdit = !props.teacherView && auth.can('timetable.create');

async function load() {
  loading.value = true;
  error.value = null;
  try {
    entries.value = await timetableApi.get(props.teacherView ? {} : { class_id: classId.value || undefined });
  } catch (e) {
    error.value = e;
  } finally {
    loading.value = false;
  }
}
watch(classId, load);
load();

const open = ref(false);
const form = reactive({ subject_id: '', teacher_id: '', day: 0, start: '08:00', end: '09:00', room: '' });
const saving = ref(false);

async function add() {
  saving.value = true;
  try {
    await timetableApi.create({ ...form, class_id: classId.value, teacher_id: form.teacher_id || null });
    ui.toast('Cours ajouté.');
    open.value = false;
    load();
  } catch (e) {
    ui.error(e);
  } finally {
    saving.value = false;
  }
}

async function remove(entry) {
  if (!(await ui.confirm({ title: 'Retirer ce cours ?', message: `${entry.subject} · ${DAYS[entry.day]} ${entry.start}–${entry.end}`, confirmLabel: 'Retirer', danger: true }))) return;
  try {
    await timetableApi.remove(entry.id);
    entries.value = entries.value.filter((e) => e.id !== entry.id);
  } catch (e) {
    ui.error(e);
  }
}
</script>

<template>
  <div class="space-y-5">
    <PageHeader :title="teacherView ? 'Mon emploi du temps' : 'Emploi du temps'" :subtitle="teacherView ? 'Vos cours de la semaine.' : 'Les chevauchements de classe, d’enseignant et de salle sont refusés.'">
      <template v-if="canEdit" #actions><button type="button" class="btn btn-primary" :disabled="!classId" @click="open = true"><AppIcon name="plus" class="size-4" />Ajouter un cours</button></template>
    </PageHeader>

    <div v-if="!teacherView" class="card p-4 sm:w-72">
      <label for="tt-class" class="label">Classe</label>
      <select id="tt-class" v-model="classId" class="input mt-1"><option v-for="c in OPTIONS.classes" :key="c.value" :value="c.value">{{ c.label }}</option></select>
    </div>

    <LoadingState v-if="loading" variant="detail" />
    <div v-else-if="error" class="card"><ErrorState :message="error.message" @retry="load" /></div>
    <div v-else class="card overflow-x-auto p-4"><TimetableGrid :entries="entries" :show-class="teacherView" :removable="canEdit" @remove="remove" /></div>

    <BaseModal :open="open" title="Ajouter un cours" @close="open = false">
      <form id="tt-form" class="grid gap-4 sm:grid-cols-2" @submit.prevent="add">
        <div class="flex flex-col gap-1"><label for="tt-subject" class="label">Matière *</label><select id="tt-subject" v-model="form.subject_id" required class="input"><option value="" disabled>Choisir</option><option v-for="s in OPTIONS.subjects" :key="s.value" :value="s.value">{{ s.label }}</option></select></div>
        <div class="flex flex-col gap-1"><label for="tt-teacher" class="label">Enseignant</label><select id="tt-teacher" v-model="form.teacher_id" class="input"><option value="">—</option><option v-for="t in OPTIONS.teachers" :key="t.value" :value="t.value">{{ t.label }}</option></select></div>
        <div class="flex flex-col gap-1"><label for="tt-day" class="label">Jour</label><select id="tt-day" v-model.number="form.day" class="input"><option v-for="(d, i) in DAYS" :key="d" :value="i">{{ d }}</option></select></div>
        <div class="flex flex-col gap-1"><label for="tt-room" class="label">Salle</label><input id="tt-room" v-model="form.room" class="input" /></div>
        <div class="flex flex-col gap-1"><label for="tt-start" class="label">Début</label><input id="tt-start" v-model="form.start" type="time" required class="input" /></div>
        <div class="flex flex-col gap-1"><label for="tt-end" class="label">Fin</label><input id="tt-end" v-model="form.end" type="time" required class="input" /></div>
      </form>
      <template #footer>
        <button type="button" class="btn btn-secondary" @click="open = false">Annuler</button>
        <button type="submit" form="tt-form" class="btn btn-primary" :disabled="saving || !form.subject_id">Ajouter</button>
      </template>
    </BaseModal>
  </div>
</template>
