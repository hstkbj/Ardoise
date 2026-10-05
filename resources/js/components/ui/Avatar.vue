<script setup>
import { computed } from 'vue';
import { initials } from '@/utils/format';

const props = defineProps({
  name: { type: String, default: '' },
  src: { type: String, default: '' },
  size: { type: String, default: 'md' },
});
const sizes = { sm: 'size-7 text-[11px]', md: 'size-8 text-xs', lg: 'size-11 text-sm', xl: 'size-16 text-lg' };
const tints = ['bg-brand-100 text-brand-700', 'bg-sun-soft text-warn-700', 'bg-lavender text-lavender-700', 'bg-sky text-info-700'];
const tint = computed(() => tints[[...(props.name || '')].reduce((a, c) => a + c.charCodeAt(0), 0) % tints.length]);
</script>

<template>
  <img v-if="src" :src="src" :alt="name" class="shrink-0 rounded-full object-cover" :class="sizes[size]" />
  <span v-else class="inline-flex shrink-0 items-center justify-center rounded-full font-semibold" :class="[sizes[size], tint]" aria-hidden="true">{{ initials(name) }}</span>
</template>
