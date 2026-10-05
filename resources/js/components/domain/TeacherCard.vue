<script setup>
import Avatar from '@/components/ui/Avatar.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';

defineProps({ teacher: { type: Object, required: true } });
</script>

<template>
  <div class="card flex items-start gap-4 p-4">
    <Avatar :name="teacher.full_name" size="lg" />
    <div class="min-w-0 flex-1">
      <div class="flex items-center justify-between gap-2">
        <p class="truncate font-semibold">{{ teacher.full_name }}</p>
        <StatusBadge :status="teacher.status" />
      </div>
      <p class="truncate text-sm text-muted">{{ teacher.subjects || 'Aucune matière' }}</p>
      <p class="mt-1 text-xs text-subtle">{{ teacher.school_name || '—' }} · {{ teacher.classes_count }} classe(s)</p>
    </div>
  </div>
</template>
