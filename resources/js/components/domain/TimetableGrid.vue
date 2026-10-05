<script setup>
/**
 * Emploi du temps : « week » = grille jours × heures, « day » = liste du jour (mobile).
 * entries : [{ id, day: 0-5, start, end, subject, teacher, room, class_name, tint }]
 */
import { computed } from 'vue';
import { DAYS } from '@/utils/format';

const props = defineProps({
  entries: { type: Array, default: () => [] },
  mode: { type: String, default: 'week' },
  day: { type: Number, default: 0 },
  startHour: { type: Number, default: 7 },
  endHour: { type: Number, default: 18 },
  showClass: { type: Boolean, default: false },
  removable: { type: Boolean, default: false },
});
const emit = defineEmits(['remove']);

const HOUR_PX = 56;
const TINTS = { mint: 'bg-mint border-brand-200', sky: 'bg-sky border-[#C9DAF0]', cream: 'bg-cream border-warn-200', lavender: 'bg-lavender border-[#D9D4F2]', blush: 'bg-blush border-[#F0D3CC]' };
const toMin = (t) => {
  const [h, m] = String(t).split(':').map(Number);
  return h * 60 + m;
};
const hours = computed(() => Array.from({ length: props.endHour - props.startHour + 1 }, (_, i) => props.startHour + i));
const top = (e) => ((toMin(e.start) - props.startHour * 60) / 60) * HOUR_PX;
const height = (e) => ((toMin(e.end) - toMin(e.start)) / 60) * HOUR_PX - 4;
const dayEntries = computed(() => props.entries.filter((e) => e.day === props.day).sort((a, b) => toMin(a.start) - toMin(b.start)));
</script>

<template>
  <div v-if="mode === 'week'" class="overflow-x-auto">
    <p v-if="!entries.length" class="py-10 text-center text-sm text-muted">Aucun cours planifié.</p>
    <div v-else class="grid min-w-[860px]" :style="{ gridTemplateColumns: `56px repeat(${DAYS.length}, minmax(0, 1fr))` }">
      <div />
      <div v-for="d in DAYS" :key="d" class="border-b border-line px-2 pb-2 text-center text-[13px] font-semibold">{{ d }}</div>
      <div class="relative" :style="{ height: `${(endHour - startHour) * HOUR_PX}px` }">
        <span v-for="h in hours" :key="h" class="absolute right-2 -translate-y-1/2 text-[11px] text-subtle tabular" :style="{ top: `${(h - startHour) * HOUR_PX}px` }">{{ h }}h</span>
      </div>
      <div v-for="(d, di) in DAYS" :key="d" class="relative border-l border-line-soft" :style="{ height: `${(endHour - startHour) * HOUR_PX}px` }">
        <div v-for="h in hours" :key="h" class="absolute inset-x-0 border-t border-line-soft" :style="{ top: `${(h - startHour) * HOUR_PX}px` }" />
        <div v-for="e in entries.filter((x) => x.day === di)" :key="e.id" class="group absolute inset-x-1 overflow-hidden rounded-lg border px-2 py-1.5 text-xs" :class="TINTS[e.tint] || TINTS.mint" :style="{ top: `${top(e) + 2}px`, height: `${height(e)}px` }">
          <p class="truncate font-semibold">{{ e.subject }}</p>
          <p class="truncate text-muted">{{ e.start }} – {{ e.end }}<template v-if="e.room"> · {{ e.room }}</template></p>
          <p class="truncate text-muted">{{ showClass ? e.class_name : e.teacher }}</p>
          <button v-if="removable" type="button" class="absolute top-1 right-1 hidden rounded bg-white/80 px-1 text-[11px] text-danger-600 group-hover:block" :aria-label="`Supprimer ${e.subject}`" @click="emit('remove', e)">×</button>
        </div>
      </div>
    </div>
  </div>

  <ul v-else class="space-y-2">
    <li v-if="!dayEntries.length" class="rounded-xl bg-white p-5 text-center text-sm text-muted">Pas de cours ce jour.</li>
    <li v-for="e in dayEntries" :key="e.id" class="flex gap-4 rounded-xl border p-3.5" :class="TINTS[e.tint] || TINTS.mint">
      <div class="w-14 shrink-0 text-sm tabular"><p class="font-semibold">{{ e.start }}</p><p class="text-muted">{{ e.end }}</p></div>
      <div class="min-w-0 text-sm"><p class="font-semibold">{{ e.subject }}</p><p class="text-muted">{{ e.teacher }}<template v-if="e.room"> · {{ e.room }}</template></p></div>
    </li>
  </ul>
</template>
