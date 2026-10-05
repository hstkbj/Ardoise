<script setup>
import { computed } from 'vue';

const props = defineProps({
  value: { type: Number, required: true },
  max: { type: Number, default: 100 },
  label: { type: String, default: '' },
  track: { type: String, default: 'bg-line-soft' },
  size: { type: String, default: 'md' },
});
const pct = computed(() => Math.min(100, Math.round((props.value / (props.max || 1)) * 100)));
</script>

<template>
  <div role="progressbar" :aria-valuenow="pct" aria-valuemin="0" aria-valuemax="100" :aria-label="label || `${pct} %`" class="w-full overflow-hidden rounded-full" :class="[track, size === 'sm' ? 'h-1.5' : 'h-2.5']">
    <div class="h-full rounded-full bg-brand-600 transition-[width]" :style="{ width: `${pct}%` }" />
  </div>
</template>
