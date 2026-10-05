<script setup>
/** Histogramme sobre (HTML/CSS). items : [{ label, value }] ; threshold : ligne de référence. */
import { computed } from 'vue';

const props = defineProps({
  items: { type: Array, required: true },
  max: { type: Number, default: null },
  height: { type: Number, default: 180 },
  threshold: { type: Number, default: null },
  thresholdLabel: { type: String, default: '' },
  format: { type: Function, default: (v) => Number(v).toLocaleString('fr-FR') },
  label: { type: String, default: 'Graphique' },
});

const top = computed(() => props.max ?? Math.max(...props.items.map((i) => i.value), 1));
const h = (v) => Math.round((v / top.value) * props.height);
</script>

<template>
  <figure :aria-label="label">
    <p v-if="!items.length" class="py-10 text-center text-sm text-subtle">Pas encore de données.</p>
    <div v-else>
      <div class="relative flex items-end gap-3 border-b border-line-strong sm:gap-4" :style="{ height: `${height + 22}px` }">
        <div v-if="threshold != null" class="pointer-events-none absolute inset-x-0 z-10 border-t-[1.5px] border-dashed border-warn-700" :style="{ bottom: `${h(threshold)}px` }" />
        <div v-for="item in items" :key="item.label" class="flex flex-1 flex-col items-center justify-end gap-1.5">
          <span class="text-xs font-medium tabular">{{ format(item.value) }}</span>
          <div class="w-full max-w-12 rounded-t bg-brand-600" :style="{ height: `${h(item.value)}px` }" />
        </div>
      </div>
      <div class="mt-2 flex gap-3 text-center text-xs text-muted sm:gap-4">
        <span v-for="item in items" :key="item.label" class="flex-1 truncate">{{ item.label }}</span>
      </div>
    </div>
    <figcaption v-if="items.length && threshold != null && thresholdLabel" class="mt-3 flex items-center gap-2 text-xs text-subtle">
      <span class="w-4 border-t-[1.5px] border-dashed border-warn-700" />{{ thresholdLabel }}
    </figcaption>
  </figure>
</template>
