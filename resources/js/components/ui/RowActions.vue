<script setup>
import { onBeforeUnmount, ref } from 'vue';
import AppIcon from './AppIcon.vue';

defineProps({
  actions: { type: Array, default: () => [] },
  label: { type: String, default: 'Actions' },
});
const emit = defineEmits(['select']);

const open = ref(false);
const root = ref(null);

function onDocClick(e) {
  if (!root.value?.contains(e.target)) close();
}
function onKey(e) {
  if (e.key === 'Escape') close();
}
function toggle() {
  open.value = !open.value;
  if (open.value) {
    document.addEventListener('click', onDocClick);
    document.addEventListener('keydown', onKey);
  }
}
function close() {
  open.value = false;
  document.removeEventListener('click', onDocClick);
  document.removeEventListener('keydown', onKey);
}
function choose(a) {
  close();
  emit('select', a.key);
}
onBeforeUnmount(close);
</script>

<template>
  <div ref="root" class="relative inline-block text-left">
    <button type="button" class="btn btn-ghost btn-icon size-9" :aria-label="label" :aria-expanded="open" aria-haspopup="menu" @click.stop="toggle"><AppIcon name="more" class="size-5" stroke-width="2.6" /></button>
    <div v-if="open" role="menu" class="absolute right-0 z-20 mt-1 min-w-52 rounded-xl border border-line bg-white p-1 shadow-lg shadow-black/5">
      <button v-for="a in actions" :key="a.key" type="button" role="menuitem" class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-left text-sm hover:bg-ground" :class="a.danger ? 'text-danger-600' : 'text-ink'" @click="choose(a)">
        <AppIcon v-if="a.icon" :name="a.icon" class="size-4 opacity-80" />{{ a.label }}
      </button>
    </div>
  </div>
</template>
