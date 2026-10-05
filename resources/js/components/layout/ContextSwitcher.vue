<script setup>
/** Choix de l'établissement affiché (barre du haut de l'administration). */
import { computed } from 'vue';
import { OPTIONS } from '@/config/options';
import { useContextStore } from '@/stores/context';
import { useOptionsStore } from '@/stores/options';

const ctx = useContextStore();
const options = useOptionsStore();
const yearLabel = computed(() => OPTIONS.academicYears.find((y) => y.value === options.current.academicYear)?.label ?? '');
const termLabel = computed(() => OPTIONS.terms.find((t) => t.value === options.current.term)?.label ?? '');
</script>

<template>
  <div class="flex min-w-0 items-center gap-2">
    <label for="ctx-school" class="sr-only">Établissement</label>
    <select v-if="OPTIONS.schools.length > 1" id="ctx-school" v-model="ctx.schoolId" class="input h-9 w-auto max-w-56 pr-8 text-[13px]">
      <option value="">Tous les établissements</option>
      <option v-for="s in OPTIONS.schools" :key="s.value" :value="s.value">{{ s.label }}</option>
    </select>
    <span v-else-if="OPTIONS.schools.length" class="truncate text-[13px] font-medium text-body">{{ OPTIONS.schools[0].label }}</span>
    <span v-if="yearLabel" class="hidden rounded-md bg-ground px-2 py-1 text-xs font-medium text-muted md:inline">{{ yearLabel }}<template v-if="termLabel"> · {{ termLabel }}</template></span>
  </div>
</template>
