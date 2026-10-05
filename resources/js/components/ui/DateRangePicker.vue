<script setup>
import { useId } from 'vue';

const model = defineModel({ type: Object, default: () => ({ from: '', to: '' }) });
defineProps({ label: { type: String, default: 'Période' } });
const id = useId();

function update(key, value) {
  model.value = { ...model.value, [key]: value };
}
</script>

<template>
  <fieldset class="flex min-w-0 flex-col gap-1">
    <legend class="label mb-1">{{ label }}</legend>
    <div class="flex items-center gap-2">
      <label :for="`${id}-from`" class="sr-only">Du</label>
      <input :id="`${id}-from`" type="date" class="input" :value="model.from" @input="update('from', $event.target.value)" />
      <span class="text-sm text-subtle" aria-hidden="true">→</span>
      <label :for="`${id}-to`" class="sr-only">Au</label>
      <input :id="`${id}-to`" type="date" class="input" :value="model.to" :min="model.from" @input="update('to', $event.target.value)" />
    </div>
  </fieldset>
</template>
