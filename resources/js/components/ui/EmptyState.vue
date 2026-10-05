<script setup>
import AppIcon from './AppIcon.vue';

defineProps({
  title: { type: String, default: 'Rien à afficher' },
  description: { type: String, default: '' },
  icon: { type: String, default: 'folder' },
  compact: { type: Boolean, default: false },
});
</script>

<template>
  <div class="flex flex-col items-center text-center" :class="compact ? 'px-4 py-8' : 'px-6 py-14'">
    <div class="mb-4 flex size-12 items-center justify-center rounded-full bg-ground text-subtle"><AppIcon :name="icon" class="size-6" /></div>
    <p class="text-[15px] font-semibold">{{ title }}</p>
    <p v-if="description" class="mt-1 max-w-sm text-sm text-muted">{{ description }}</p>
    <div v-if="$slots.default" class="mt-5 flex flex-wrap justify-center gap-2"><slot /></div>
  </div>
</template>
