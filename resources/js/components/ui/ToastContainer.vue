<script setup>
import { useUiStore } from '@/stores/ui';
import AppIcon from './AppIcon.vue';

const ui = useUiStore();
const tones = {
  success: { icon: 'check', cls: 'text-brand-600' },
  error: { icon: 'alert', cls: 'text-danger-600' },
  info: { icon: 'info', cls: 'text-info-700' },
};
</script>

<template>
  <div class="pointer-events-none fixed inset-x-4 bottom-4 z-[60] flex flex-col items-center gap-2 sm:inset-x-auto sm:right-6 sm:items-end print:hidden" aria-live="polite" role="status">
    <TransitionGroup enter-active-class="transition duration-200 ease-out" enter-from-class="translate-y-2 opacity-0" leave-active-class="transition duration-150 ease-in" leave-to-class="opacity-0">
      <div v-for="t in ui.toasts" :key="t.id" class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-xl border border-line bg-white px-4 py-3 shadow-lg shadow-black/5">
        <AppIcon :name="tones[t.tone]?.icon || 'info'" class="mt-0.5 size-[18px] shrink-0" :class="tones[t.tone]?.cls" stroke-width="2.2" />
        <div class="min-w-0 flex-1 text-sm">
          <p v-if="t.title" class="font-semibold">{{ t.title }}</p>
          <p class="text-body">{{ t.message }}</p>
        </div>
        <button type="button" class="-m-1 rounded p-1 text-subtle hover:text-ink" aria-label="Fermer" @click="ui.dismiss(t.id)">
          <AppIcon name="x" class="size-4" />
        </button>
      </div>
    </TransitionGroup>
  </div>
</template>
