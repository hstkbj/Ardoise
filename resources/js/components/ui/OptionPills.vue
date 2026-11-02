<script setup>
/** Choix unique présenté en pastilles (plus rapide à lire et à régler qu'une liste déroulante). */
defineProps({
  options: { type: Array, required: true }, // [{ value, label }]
  label: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
});
const model = defineModel({ type: [String, Number, Boolean], default: '' });
</script>

<template>
  <div role="radiogroup" :aria-label="label" class="flex flex-wrap gap-1.5">
    <button
      v-for="o in options"
      :key="o.value"
      type="button"
      role="radio"
      :aria-checked="model === o.value"
      :disabled="disabled"
      class="min-h-9 rounded-lg border px-3 py-1.5 text-sm transition-colors disabled:opacity-60"
      :class="model === o.value ? 'border-brand-600 bg-brand-50 font-medium text-brand-700' : 'border-line text-body hover:bg-ground-soft'"
      @click="model = o.value"
    >{{ o.label }}</button>
  </div>
</template>
