<script setup>
defineProps({ light: { type: Boolean, default: false }, compact: { type: Boolean, default: false } });
</script>

<template>
  <span class="inline-flex items-center gap-2.5">
    <svg viewBox="0 0 32 32" class="size-8 shrink-0" aria-hidden="true">
      <rect width="32" height="32" rx="9" :fill="light ? '#ffffff' : '#1D5C4D'" />
      <path d="M9 22.5 15.2 9h1.6L23 22.5" fill="none" :stroke="light ? '#1D5C4D' : '#ffffff'" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" />
      <path d="M11.6 17.5h8.8" :stroke="light ? '#F2B33D' : '#F2B33D'" stroke-width="2.4" stroke-linecap="round" />
    </svg>
    <span v-if="!compact" class="text-[17px] font-semibold tracking-tight" :class="light ? 'text-white' : 'text-ink'">Ardoise</span>
  </span>
</template>
