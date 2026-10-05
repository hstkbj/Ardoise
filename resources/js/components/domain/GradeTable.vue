<script setup>
/**
 * Saisie rapide des notes.
 * v-model:rows = [{ student_id, full_name, matricule, score: '14,5', absent, comment }]
 * Entrée / ↓ : élève suivant · ↑ : élève précédent.
 */
import { nextTick, ref } from 'vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import { parseGrade } from '@/utils/format';

const rows = defineModel('rows', { type: Array, required: true });
const props = defineProps({
  maxScore: { type: Number, default: 20 },
  coefficient: { type: Number, default: 1 },
  locked: { type: Boolean, default: false },
});
const emit = defineEmits(['change']);

const inputs = ref([]);

function rowStatus(row) {
  if (row.absent) return 'absent';
  const n = parseGrade(row.score);
  if (n === null) return 'missing';
  if (Number.isNaN(n) || n < 0 || n > props.maxScore) return 'error';
  return 'entered';
}
function focusRow(i) {
  nextTick(() => inputs.value[i]?.focus());
}
function onKey(e, i) {
  if (e.key === 'Enter' || e.key === 'ArrowDown') {
    e.preventDefault();
    let j = i + 1;
    while (j < rows.value.length && rows.value[j].absent) j++;
    focusRow(j);
  } else if (e.key === 'ArrowUp') {
    e.preventDefault();
    focusRow(i - 1);
  }
}
function update(i, patch) {
  rows.value = rows.value.map((r, idx) => (idx === i ? { ...r, ...patch } : r));
  emit('change');
}
defineExpose({ rowStatus });
</script>

<template>
  <div class="overflow-x-auto">
    <table class="w-full min-w-[820px] border-collapse text-sm">
      <caption class="sr-only">Saisie des notes</caption>
      <thead>
        <tr class="bg-ground-soft text-left text-xs text-muted">
          <th scope="col" class="w-12 border-b border-line-soft py-3 pl-5 font-medium">N°</th>
          <th scope="col" class="border-b border-line-soft px-3 py-3 font-medium">Élève</th>
          <th scope="col" class="w-16 border-b border-line-soft px-3 py-3 text-center font-medium">Abs.</th>
          <th scope="col" class="w-32 border-b border-line-soft px-3 py-3 font-medium">Note /{{ maxScore }}</th>
          <th scope="col" class="w-16 border-b border-line-soft px-3 py-3 text-center font-medium">Coef.</th>
          <th scope="col" class="border-b border-line-soft px-3 py-3 font-medium">Commentaire</th>
          <th scope="col" class="w-28 border-b border-line-soft py-3 pr-5 pl-3 font-medium">Statut</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(row, i) in rows" :key="row.student_id" class="border-b border-line-soft align-top last:border-b-0 focus-within:bg-brand-50">
          <td class="py-3.5 pl-5 tabular text-subtle">{{ i + 1 }}</td>
          <td class="px-3 py-2.5"><p class="font-medium">{{ row.full_name }}</p><p class="text-xs text-subtle">{{ row.matricule }}</p></td>
          <td class="px-3 py-3.5 text-center">
            <input type="checkbox" class="size-[18px] accent-brand-600" :checked="row.absent" :disabled="locked" :aria-label="`Absent : ${row.full_name}`" @change="update(i, { absent: $event.target.checked, score: $event.target.checked ? '' : row.score })" />
          </td>
          <td class="px-3 py-2">
            <input :ref="(el) => (inputs[i] = el)" :value="row.score" inputmode="decimal" autocomplete="off" class="input h-9 w-24 text-right text-[15px] tabular" :placeholder="row.absent ? 'Absent' : '—'" :disabled="locked || row.absent" :aria-label="`Note de ${row.full_name}`" :aria-invalid="rowStatus(row) === 'error'" :aria-describedby="rowStatus(row) === 'error' ? `err-${row.student_id}` : undefined" @input="update(i, { score: $event.target.value })" @keydown="onKey($event, i)" />
            <p v-if="rowStatus(row) === 'error'" :id="`err-${row.student_id}`" class="mt-1 text-xs text-danger-600">Entre 0 et {{ maxScore }}.</p>
          </td>
          <td class="px-3 py-3.5 text-center text-muted tabular">{{ coefficient }}</td>
          <td class="px-3 py-2">
            <input :value="row.comment" class="input h-9" placeholder="Ajouter un commentaire" :disabled="locked" :aria-label="`Commentaire pour ${row.full_name}`" @input="update(i, { comment: $event.target.value })" />
          </td>
          <td class="py-3.5 pr-5 pl-3"><StatusBadge :status="rowStatus(row)" /></td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
