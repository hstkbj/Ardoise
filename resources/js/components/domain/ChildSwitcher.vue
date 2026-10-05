<script setup>
defineProps({
  items: { type: Array, required: true },
  selectedId: { type: Number, default: null },
});
const emit = defineEmits(['select']);
</script>

<template>
  <div v-if="items.length > 1" role="group" aria-label="Choisir un enfant" class="grid gap-1 rounded-xl bg-line-soft p-1" :style="{ gridTemplateColumns: `repeat(${items.length}, minmax(0, 1fr))` }">
    <button v-for="c in items" :key="c.id" type="button" class="min-h-[52px] rounded-[10px] px-3 py-2 text-left" :class="c.id === selectedId ? 'bg-white shadow-sm' : 'hover:bg-white/50'" :aria-pressed="c.id === selectedId" @click="emit('select', c.id)">
      <span class="block text-sm" :class="c.id === selectedId ? 'font-semibold' : 'font-medium text-body'">{{ c.first_name }}</span>
      <span class="block text-xs text-muted">{{ c.class_name }}<template v-if="c.school_name"> · {{ c.school_name }}</template></span>
    </button>
  </div>
</template>
