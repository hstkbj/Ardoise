<script setup>
/** Appel d'une séance. v-model:rows = [{ student_id, full_name, matricule, status, minutes_late, justified, reason }] */
const rows = defineModel('rows', { type: Array, required: true });
defineProps({ locked: { type: Boolean, default: false } });

const OPTIONS = [
  { value: 'present', label: 'Présent', on: 'border-brand-600 bg-brand-100 text-brand-700' },
  { value: 'absent', label: 'Absent', on: 'border-danger-600 bg-danger-50 text-danger-600' },
  { value: 'late', label: 'Retard', on: 'border-info-700 bg-info-50 text-info-700' },
];

function update(i, patch) {
  rows.value = rows.value.map((r, idx) => (idx === i ? { ...r, ...patch } : r));
}
</script>

<template>
  <ul class="divide-y divide-line-soft">
    <li v-for="(row, i) in rows" :key="row.student_id" class="flex flex-wrap items-center gap-3 px-5 py-3">
      <div class="min-w-48 flex-1"><p class="font-medium">{{ row.full_name }}</p><p class="text-xs text-subtle">{{ row.matricule }}</p></div>
      <div role="radiogroup" :aria-label="`Présence de ${row.full_name}`" class="flex gap-1.5">
        <label v-for="o in OPTIONS" :key="o.value" class="flex h-9 cursor-pointer items-center rounded-lg border px-3 text-[13px] font-medium select-none" :class="row.status === o.value ? o.on : 'border-line bg-white text-muted hover:bg-ground'">
          <input type="radio" class="sr-only" :name="`att-${row.student_id}`" :value="o.value" :checked="row.status === o.value" :disabled="locked" @change="update(i, { status: o.value })" />
          {{ o.label }}
        </label>
      </div>
      <div v-if="row.status !== 'present'" class="flex w-full flex-wrap items-center gap-3 sm:w-auto">
        <input v-if="row.status === 'late'" type="number" min="1" class="input h-9 w-28" :value="row.minutes_late" placeholder="Minutes" :aria-label="`Minutes de retard de ${row.full_name}`" @input="update(i, { minutes_late: $event.target.value })" />
        <label class="flex items-center gap-2 text-[13px] text-body"><input type="checkbox" class="size-4 accent-brand-600" :checked="row.justified" @change="update(i, { justified: $event.target.checked })" />Justifié</label>
        <input class="input h-9 w-full sm:w-56" :value="row.reason" placeholder="Motif (facultatif)" :aria-label="`Motif pour ${row.full_name}`" @input="update(i, { reason: $event.target.value })" />
      </div>
    </li>
  </ul>
</template>
