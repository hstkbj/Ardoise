<script setup>
import { computed } from 'vue';
import { STATUSES } from '@/config/statuses';

const props = defineProps({
  status: { type: String, default: '' },
  label: { type: String, default: '' },
  tone: { type: String, default: '' },
});

const TONES = {
  success: 'bg-brand-100 text-brand-700',
  warning: 'bg-warn-50 text-warn-700',
  danger: 'bg-danger-50 text-danger-600',
  info: 'bg-info-50 text-info-700',
  neutral: 'bg-[#F1F1EE] text-muted',
};

const def = computed(() => STATUSES[props.status] || {});
const text = computed(() => props.label || def.value.label || props.status || '—');
const cls = computed(() => TONES[props.tone || def.value.tone || 'neutral']);
</script>

<template>
  <span class="inline-flex items-center rounded px-2 py-0.5 text-xs font-semibold whitespace-nowrap" :class="cls">{{ text }}</span>
</template>
