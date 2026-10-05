<script setup>
/** Appel d'une séance : classe, date, créneau ; les parents sont prévenus des absences. */
import { computed, ref, watch } from 'vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import LoadingState from '@/components/ui/LoadingState.vue';
import ErrorState from '@/components/ui/ErrorState.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import AppIcon from '@/components/ui/AppIcon.vue';
import AttendanceTable from '@/components/domain/AttendanceTable.vue';
import { attendanceApi, timetableApi } from '@/services/api';
import { OPTIONS } from '@/config/options';
import { useUiStore } from '@/stores/ui';
import { today } from '@/utils/format';

const props = defineProps({ historyPath: { type: String, default: '/admin/attendance/history' } });
const ui = useUiStore();

const classId = ref(OPTIONS.classes[0]?.value ?? '');
const date = ref(today());
const slot = ref('08:00');
const timetable = ref([]);
const rows = ref([]);
const loading = ref(false);
const error = ref(null);
const saving = ref(false);
const dirty = ref(false);

// Créneaux proposés : ceux de l'emploi du temps de la classe pour ce jour
const dayIndex = computed(() => (new Date(`${date.value}T12:00:00`).getDay() + 6) % 7);
const slots = computed(() => timetable.value.filter((e) => e.day === dayIndex.value).map((e) => ({ value: e.start, label: `${e.start} – ${e.end} · ${e.subject}` })));

async function loadTimetable() {
  if (!classId.value) return;
  timetable.value = await timetableApi.get({ class_id: classId.value }).catch(() => []);
  if (slots.value.length && !slots.value.some((s) => s.value === slot.value)) slot.value = slots.value[0].value;
}

async function loadRoster() {
  if (!classId.value || !/^\d{2}:\d{2}$/.test(slot.value)) return;
  loading.value = true;
  error.value = null;
  try {
    rows.value = await attendanceApi.roster({ class_id: classId.value, date: date.value, slot: slot.value });
    dirty.value = false;
  } catch (e) {
    error.value = e;
  } finally {
    loading.value = false;
  }
}

watch(classId, async () => {
  await loadTimetable();
  loadRoster();
});
watch(date, async () => {
  if (slots.value.length && !slots.value.some((s) => s.value === slot.value)) slot.value = slots.value[0].value;
  loadRoster();
});
watch(slot, loadRoster);
loadTimetable().then(loadRoster);

const counts = computed(() => ({
  present: rows.value.filter((r) => r.status === 'present').length,
  absent: rows.value.filter((r) => r.status === 'absent').length,
  late: rows.value.filter((r) => r.status === 'late').length,
}));
const alreadyRecorded = computed(() => rows.value.some((r) => r.recorded));

function allPresent() {
  rows.value = rows.value.map((r) => ({ ...r, status: 'present' }));
  dirty.value = true;
}

async function save() {
  saving.value = true;
  try {
    const out = await attendanceApi.save({
      class_id: classId.value,
      date: date.value,
      slot: slot.value,
      records: rows.value.map((r) => ({ student_id: r.student_id, status: r.status, minutes_late: r.status === 'late' ? Number(r.minutes_late) || null : null, justified: !!r.justified, reason: r.reason || null })),
    });
    ui.toast(out?.message || 'Appel enregistré.');
    loadRoster();
  } catch (e) {
    ui.error(e);
  } finally {
    saving.value = false;
  }
}
</script>

<template>
  <div class="space-y-5">
    <PageHeader title="Appel" subtitle="Les parents sont notifiés dès l’enregistrement d’une absence ou d’un retard.">
      <template #actions><RouterLink :to="historyPath" class="btn btn-secondary"><AppIcon name="list" class="size-4" />Historique</RouterLink></template>
    </PageHeader>

    <div class="card grid gap-3 p-4 sm:grid-cols-3">
      <div class="flex flex-col gap-1"><label for="att-class" class="label">Classe</label><select id="att-class" v-model="classId" class="input"><option v-for="c in OPTIONS.classes" :key="c.value" :value="c.value">{{ c.label }}</option></select></div>
      <div class="flex flex-col gap-1"><label for="att-date" class="label">Date</label><input id="att-date" v-model="date" type="date" :max="today()" class="input" /></div>
      <div class="flex flex-col gap-1">
        <label for="att-slot" class="label">Séance</label>
        <select v-if="slots.length" id="att-slot" v-model="slot" class="input"><option v-for="s in slots" :key="s.value" :value="s.value">{{ s.label }}</option></select>
        <input v-else id="att-slot" v-model="slot" type="time" class="input" />
      </div>
    </div>

    <EmptyState v-if="!OPTIONS.classes.length" title="Aucune classe accessible" icon="layers" class="card" />
    <LoadingState v-else-if="loading" />
    <div v-else-if="error" class="card"><ErrorState :message="error.message" @retry="loadRoster" /></div>
    <section v-else class="card">
      <div class="flex flex-wrap items-center gap-3 border-b border-line-soft px-5 py-3.5 text-sm">
        <span class="font-medium">{{ rows.length }} élèves</span>
        <span class="text-brand-700">{{ counts.present }} présent(s)</span>
        <span class="text-danger-600">{{ counts.absent }} absent(s)</span>
        <span class="text-info-700">{{ counts.late }} retard(s)</span>
        <span v-if="alreadyRecorded" class="rounded-md bg-ground px-2 py-0.5 text-xs text-muted">Appel déjà fait — modification</span>
        <button type="button" class="btn btn-ghost btn-sm ml-auto" @click="allPresent">Tous présents</button>
      </div>
      <EmptyState v-if="!rows.length" title="Aucun élève inscrit" icon="users" />
      <AttendanceTable v-else v-model:rows="rows" @update:rows="dirty = true" />
      <div class="flex justify-end border-t border-line-soft px-5 py-4">
        <button type="button" class="btn btn-primary" :disabled="saving || !rows.length" @click="save"><AppIcon name="check" class="size-4" />{{ alreadyRecorded ? 'Mettre à jour l’appel' : 'Enregistrer l’appel' }}</button>
      </div>
    </section>
  </div>
</template>
