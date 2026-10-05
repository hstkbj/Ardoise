<script setup>
import { ref, watch } from 'vue';
import ParentSection from '@/components/domain/ParentSection.vue';
import TimetableGrid from '@/components/domain/TimetableGrid.vue';
import { parentApi } from '@/services/api';
import { useParentChildren } from '@/composables/useParentChildren';
import { DAYS } from '@/utils/format';

const { child } = useParentChildren();
const entries = ref([]);
const day = ref(Math.min(5, (new Date().getDay() + 6) % 7));
watch(() => child.value?.id, async (id) => {
  if (id) entries.value = await parentApi.timetable(id).catch(() => []);
}, { immediate: true });
</script>

<template>
  <ParentSection title="Emploi du temps" wide>
    <div class="flex gap-1 overflow-x-auto md:hidden" role="tablist" aria-label="Jour">
      <button v-for="(d, i) in DAYS" :key="d" type="button" role="tab" :aria-selected="day === i" class="h-9 shrink-0 rounded-full px-3 text-[13px] font-medium" :class="day === i ? 'bg-brand-600 text-white' : 'bg-white text-body'" @click="day = i">{{ d.slice(0, 3) }}</button>
    </div>
    <div class="card overflow-x-auto p-3">
      <div class="md:hidden"><TimetableGrid :entries="entries" mode="day" :day="day" /></div>
      <div class="hidden md:block"><TimetableGrid :entries="entries" /></div>
    </div>
  </ParentSection>
</template>
