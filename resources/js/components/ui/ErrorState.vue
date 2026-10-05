<script setup>
import AppIcon from './AppIcon.vue';

defineProps({
  message: { type: String, default: 'Impossible de charger les données.' },
  compact: { type: Boolean, default: false },
});
const emit = defineEmits(['retry']);
</script>

<template>
  <div role="alert" class="flex flex-col items-center text-center" :class="compact ? 'px-4 py-8' : 'px-6 py-14'">
    <div class="mb-4 flex size-12 items-center justify-center rounded-full bg-danger-50 text-danger-600"><AppIcon name="alert" class="size-6" /></div>
    <p class="text-[15px] font-semibold">Une erreur est survenue</p>
    <p class="mt-1 max-w-sm text-sm text-muted">{{ message }}</p>
    <button type="button" class="btn btn-secondary mt-5" @click="emit('retry')"><AppIcon name="refresh" class="size-4" /> Réessayer</button>
  </div>
</template>
