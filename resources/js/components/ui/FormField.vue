<script setup>
/** Champ de formulaire piloté par configuration (voir config/resources.js). */
import { computed, useId } from 'vue';
import { OPTIONS } from '@/config/options';

const props = defineProps({
  field: { type: Object, required: true },
  error: { type: String, default: '' },
});
const model = defineModel();
const id = useId();

const type = computed(() => props.field.type || 'text');
const options = computed(() => props.field.options || OPTIONS[props.field.optionsKey] || []);
const describedBy = computed(() => [props.field.hint ? `${id}-hint` : null, props.error ? `${id}-err` : null].filter(Boolean).join(' ') || undefined);

function toggleValue(value) {
  const list = Array.isArray(model.value) ? [...model.value] : [];
  const i = list.findIndex((v) => String(v) === String(value));
  i === -1 ? list.push(value) : list.splice(i, 1);
  model.value = list;
}
function isChecked(value) {
  return Array.isArray(model.value) && model.value.some((v) => String(v) === String(value));
}
const fileLabel = computed(() => {
  const v = model.value;
  if (typeof File !== 'undefined' && v instanceof File) return v.name;
  if (Array.isArray(v) && v.length) return `${v.length} fichier(s)`;
  return 'ou glissez-le ici';
});
function onFile(e) {
  const files = [...e.target.files];
  model.value = props.field.multiple ? files : files[0] || null;
}
</script>

<template>
  <div class="flex flex-col gap-1.5" :class="field.span === 2 ? 'sm:col-span-2' : ''">
    <template v-if="type === 'checkboxes'">
      <fieldset :aria-describedby="describedBy">
        <legend class="label mb-2">{{ field.label }}<span v-if="field.required" class="text-danger-600"> *</span></legend>
        <p v-if="!options.length" class="text-sm text-subtle">Aucun élément disponible.</p>
        <div class="grid max-h-72 gap-2 overflow-y-auto sm:grid-cols-2 lg:grid-cols-3">
          <label v-for="o in options" :key="o.value" class="flex min-h-10 cursor-pointer items-center gap-2.5 rounded-lg border border-line px-3 py-2 text-sm hover:bg-ground-soft has-[:checked]:border-brand-300 has-[:checked]:bg-brand-50">
            <input type="checkbox" class="size-4 accent-brand-600" :checked="isChecked(o.value)" @change="toggleValue(o.value)" />
            {{ o.label }}
          </label>
        </div>
      </fieldset>
    </template>

    <template v-else>
      <label :for="id" class="label">{{ field.label }}<span v-if="field.required" class="text-danger-600"> *</span></label>

      <select v-if="type === 'select'" :id="id" v-model="model" class="input" :aria-invalid="!!error" :aria-describedby="describedBy" :required="field.required">
        <option value="">Choisir…</option>
        <option v-for="o in options" :key="o.value" :value="o.value">{{ o.label }}</option>
      </select>

      <textarea v-else-if="type === 'textarea'" :id="id" v-model="model" class="input" :rows="field.rows || 4" :placeholder="field.placeholder" :aria-invalid="!!error" :aria-describedby="describedBy" />

      <label v-else-if="type === 'file'" :for="id" class="flex min-h-20 cursor-pointer flex-col items-center justify-center gap-1 rounded-lg border border-dashed border-line-strong bg-ground-soft px-4 py-4 text-center text-sm text-muted hover:border-brand-300">
        <span class="font-medium text-brand-600">Choisir un fichier</span>
        <span class="text-xs">{{ fileLabel }}</span>
        <input :id="id" type="file" class="sr-only" :accept="field.accept" :multiple="field.multiple" :aria-describedby="describedBy" @change="onFile" />
      </label>

      <input v-else :id="id" v-model="model" :type="type" class="input" :placeholder="field.placeholder" :required="field.required" :aria-invalid="!!error" :aria-describedby="describedBy" :inputmode="type === 'number' ? 'decimal' : undefined" :step="type === 'number' ? 'any' : undefined" />
    </template>

    <p v-if="field.hint" :id="`${id}-hint`" class="text-xs text-subtle">{{ field.hint }}</p>
    <p v-if="error" :id="`${id}-err`" class="text-xs text-danger-600" role="alert">{{ error }}</p>
  </div>
</template>
