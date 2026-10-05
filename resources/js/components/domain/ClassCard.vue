<script setup>
import ProgressBar from '@/components/ui/ProgressBar.vue';

defineProps({ classroom: { type: Object, required: true }, to: { type: [String, Object], default: null } });
</script>

<template>
  <component :is="to ? 'RouterLink' : 'div'" :to="to" class="card block p-5" :class="to ? 'hover:border-brand-300' : ''">
    <div class="flex items-baseline justify-between gap-2">
      <p class="text-lg font-semibold">{{ classroom.name }}</p>
      <span v-if="classroom.room" class="text-xs text-subtle">Salle {{ classroom.room }}</span>
    </div>
    <p class="text-sm text-muted">{{ classroom.school_name }}<template v-if="classroom.head_teacher"> · {{ classroom.head_teacher }}</template></p>
    <div class="mt-4 flex justify-between text-xs text-muted">
      <span>Effectif</span>
      <span class="tabular">{{ classroom.students_count }}<template v-if="classroom.capacity"> / {{ classroom.capacity }}</template></span>
    </div>
    <ProgressBar class="mt-1.5" size="sm" :value="classroom.students_count" :max="classroom.capacity || classroom.students_count || 1" :label="`Effectif ${classroom.students_count}`" />
  </component>
</template>
