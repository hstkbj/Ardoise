<script setup>
defineProps({
  label: { type: String, required: true },
  value: { type: [String, Number], default: '—' },
  unit: { type: String, default: '' },
  hint: { type: String, default: '' },
  hintTone: { type: String, default: 'muted' },
  plain: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
});
const hintCls = { muted: 'text-subtle', positive: 'text-brand-600', warning: 'text-warn-700' };
</script>

<template>
  <div :class="plain ? 'px-5 py-4' : 'card px-5 py-4'">
    <p class="text-[13px] text-muted">{{ label }}</p>
    <div v-if="loading" class="skeleton mt-2.5 h-6 w-20" />
    <p v-else class="mt-1.5 text-[24px] leading-tight font-semibold tabular">{{ value }}<span v-if="unit" class="ml-1 text-sm font-medium text-subtle">{{ unit }}</span></p>
    <p v-if="hint && !loading" class="mt-1 text-xs" :class="hintCls[hintTone]">{{ hint }}</p>
  </div>
</template>
