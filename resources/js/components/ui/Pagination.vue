<script setup>
import { computed } from 'vue';
import AppIcon from './AppIcon.vue';

const page = defineModel({ type: Number, default: 1 });
const props = defineProps({
  meta: { type: Object, required: true },
  label: { type: String, default: 'éléments' },
});

const from = computed(() => (props.meta.total ? (props.meta.current_page - 1) * props.meta.per_page + 1 : 0));
const to = computed(() => Math.min(props.meta.current_page * props.meta.per_page, props.meta.total));

const pages = computed(() => {
  const last = props.meta.last_page;
  const cur = props.meta.current_page;
  const set = new Set([1, last, cur - 1, cur, cur + 1].filter((p) => p >= 1 && p <= last));
  const sorted = [...set].sort((a, b) => a - b);
  const out = [];
  sorted.forEach((p, i) => {
    if (i && p - sorted[i - 1] > 1) out.push('…');
    out.push(p);
  });
  return out;
});
</script>

<template>
  <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-3.5 text-[13px] text-muted">
    <span class="tabular">{{ from }}–{{ to }} sur {{ meta.total }} {{ label }}</span>
    <nav v-if="meta.last_page > 1" aria-label="Pagination" class="flex items-center gap-1">
      <button type="button" class="btn btn-secondary btn-icon size-9" :disabled="page <= 1" aria-label="Page précédente" @click="page--"><AppIcon name="chevron-left" class="size-4" /></button>
      <template v-for="(p, i) in pages" :key="i">
        <span v-if="p === '…'" class="px-1">…</span>
        <button v-else type="button" class="size-9 rounded-lg border text-sm font-medium tabular" :class="p === page ? 'border-brand-600 bg-brand-100 text-brand-700' : 'border-line bg-white text-ink hover:bg-ground'" :aria-current="p === page ? 'page' : undefined" @click="page = p">{{ p }}</button>
      </template>
      <button type="button" class="btn btn-secondary btn-icon size-9" :disabled="page >= meta.last_page" aria-label="Page suivante" @click="page++"><AppIcon name="chevron-right" class="size-4" /></button>
    </nav>
  </div>
</template>
