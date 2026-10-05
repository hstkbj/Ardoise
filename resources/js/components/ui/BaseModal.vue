<script setup>
import { nextTick, onBeforeUnmount, ref, watch } from 'vue';
import AppIcon from './AppIcon.vue';

const props = defineProps({
  open: { type: Boolean, default: false },
  title: { type: String, default: '' },
  description: { type: String, default: '' },
  size: { type: String, default: 'md' },
});
const emit = defineEmits(['close']);

const panel = ref(null);
const widths = { sm: 'max-w-md', md: 'max-w-lg', lg: 'max-w-3xl' };
let previouslyFocused = null;

function onKey(e) {
  if (e.key === 'Escape') emit('close');
}

watch(
  () => props.open,
  async (open) => {
    if (open) {
      previouslyFocused = document.activeElement;
      document.addEventListener('keydown', onKey);
      await nextTick();
      panel.value?.querySelector('input, select, textarea, button:not([data-close])')?.focus();
    } else {
      document.removeEventListener('keydown', onKey);
      previouslyFocused?.focus?.();
    }
  },
);
onBeforeUnmount(() => document.removeEventListener('keydown', onKey));
</script>

<template>
  <Teleport to="body">
    <Transition enter-active-class="transition duration-150" enter-from-class="opacity-0" leave-active-class="transition duration-100" leave-to-class="opacity-0">
      <div v-if="open" class="fixed inset-0 z-50 flex items-end justify-center bg-ink/40 p-0 sm:items-center sm:p-6" @mousedown.self="emit('close')">
        <div ref="panel" role="dialog" aria-modal="true" :aria-label="title" class="max-h-[92vh] w-full overflow-y-auto rounded-t-2xl bg-white shadow-xl sm:rounded-2xl" :class="widths[size]">
          <div class="flex items-start justify-between gap-4 px-6 pt-5">
            <div>
              <h2 class="text-lg font-semibold">{{ title }}</h2>
              <p v-if="description" class="mt-1 text-sm text-muted">{{ description }}</p>
            </div>
            <button type="button" data-close class="btn btn-ghost btn-icon -mt-1 -mr-2" aria-label="Fermer" @click="emit('close')">
              <AppIcon name="x" class="size-5" />
            </button>
          </div>
          <div class="px-6 py-5"><slot /></div>
          <div v-if="$slots.footer" class="flex flex-col-reverse gap-2 border-t border-line-soft px-6 py-4 sm:flex-row sm:justify-end">
            <slot name="footer" />
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
