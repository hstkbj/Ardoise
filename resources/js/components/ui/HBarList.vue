<script setup>
import { computed } from 'vue';

const props = defineProps({
  items: { type: Array, required: true },
  format: { type: Function, default: (v) => Number(v).toLocaleString('fr-FR') },
  showShare: { type: Boolean, default: true },
});
const total = computed(() => props.items.reduce((a, i) => a + i.value, 0) || 1);
const max = computed(() => Math.max(...props.items.map((i) => i.value), 1));
</script>

<template>
  <p v-if="!items.length" class="py-6 text-center text-sm text-subtle">Pas encore de données.</p>
  <ul v-else class="space-y-4">
    <li v-for="item in items" :key="item.label">
      <div class="flex justify-between gap-3 text-[13px]">
        <span class="truncate">{{ item.label }}</span>
        <span class="shrink-0 tabular text-muted">{{ format(item.value) }}<template v-if="showShare"> · {{ Math.round((item.value / total) * 100) }} %</template></span>
      </div>
      <div class="mt-1.5 h-2 rounded-full bg-line-soft"><div class="h-2 rounded-full bg-brand-600" :style="{ width: `${(item.value / max) * 100}%` }" /></div>
    </li>
  </ul>
</template>
