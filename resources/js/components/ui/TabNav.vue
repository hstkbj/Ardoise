<script setup>
const model = defineModel({ type: String, required: true });
defineProps({ tabs: { type: Array, required: true }, label: { type: String, default: 'Sections' } });

function onKey(e, tabs) {
  const i = tabs.findIndex((t) => t.key === model.value);
  if (e.key === 'ArrowRight') model.value = tabs[(i + 1) % tabs.length].key;
  if (e.key === 'ArrowLeft') model.value = tabs[(i - 1 + tabs.length) % tabs.length].key;
}
</script>

<template>
  <div class="-mx-1 overflow-x-auto border-b border-line" role="tablist" :aria-label="label" @keydown="onKey($event, tabs)">
    <div class="flex min-w-max gap-1 px-1">
      <button v-for="t in tabs" :key="t.key" type="button" role="tab" :aria-selected="model === t.key" :tabindex="model === t.key ? 0 : -1" class="relative -mb-px flex h-11 items-center gap-2 border-b-2 px-3 text-sm font-medium whitespace-nowrap" :class="model === t.key ? 'border-brand-600 text-ink' : 'border-transparent text-muted hover:text-ink'" @click="model = t.key">
        {{ t.label }}
        <span v-if="t.count != null" class="rounded bg-ground px-1.5 text-xs tabular text-muted">{{ t.count }}</span>
      </button>
    </div>
  </div>
</template>
