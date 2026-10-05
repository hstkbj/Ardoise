<script setup>
import AppIcon from './AppIcon.vue';

const model = defineModel({ type: String, default: '' });
defineProps({
  placeholder: { type: String, default: 'Rechercher…' },
  label: { type: String, default: 'Rechercher' },
});
</script>

<template>
  <div class="relative">
    <AppIcon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-subtle" />
    <input v-model="model" type="search" class="input pr-9 pl-9" :placeholder="placeholder" :aria-label="label" />
    <button v-if="model" type="button" class="absolute top-1/2 right-1 flex size-8 -translate-y-1/2 items-center justify-center rounded text-subtle hover:text-ink" aria-label="Effacer la recherche" @click="model = ''">
      <AppIcon name="x" class="size-4" />
    </button>
  </div>
</template>
