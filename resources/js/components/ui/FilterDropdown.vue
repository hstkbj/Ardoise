<script setup>
import { useId } from 'vue';

const model = defineModel({ type: [String, Number], default: '' });
defineProps({
  label: { type: String, required: true },
  options: { type: Array, default: () => [] },
  allLabel: { type: String, default: 'Tous' },
  hideLabel: { type: Boolean, default: false },
});
const id = useId();
</script>

<template>
  <div class="flex min-w-0 flex-col gap-1">
    <label :for="id" class="label" :class="{ 'sr-only': hideLabel }">{{ label }}</label>
    <select :id="id" v-model="model" class="input pr-8">
      <option value="">{{ allLabel }}</option>
      <option v-for="o in options" :key="o.value" :value="o.value">{{ o.label }}</option>
    </select>
  </div>
</template>
