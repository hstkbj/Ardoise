<script setup>
import Avatar from '@/components/ui/Avatar.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';

defineProps({
  student: { type: Object, required: true },
  to: { type: [String, Object], default: null },
});
</script>

<template>
  <component :is="to ? 'RouterLink' : 'div'" :to="to" class="card flex items-center gap-4 p-4" :class="to ? 'hover:border-brand-300' : ''">
    <Avatar :name="student.full_name" size="lg" />
    <div class="min-w-0 flex-1">
      <p class="truncate font-semibold">{{ student.full_name }}</p>
      <p class="truncate text-sm text-muted">{{ student.class_name }} · {{ student.school_name }}</p>
      <p class="text-xs text-subtle">{{ student.matricule }}</p>
    </div>
    <div class="text-right">
      <StatusBadge v-if="student.status && student.status !== 'active'" :status="student.status" />
      <p v-else-if="student.average != null" class="text-lg font-semibold tabular">{{ Number(student.average).toLocaleString('fr-FR') }}<span class="text-xs text-subtle">/20</span></p>
    </div>
  </component>
</template>
