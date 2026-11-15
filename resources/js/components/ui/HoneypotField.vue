<script setup>
/** Champ piège anti-robots : invisible pour les personnes, rempli par les robots (voir SpamGuard). */
const model = defineModel({ type: String, default: '' });
</script>

<template>
  <div class="absolute -left-[9999px] h-0 w-0 overflow-hidden" aria-hidden="true">
    <label>Ne pas remplir ce champ<input v-model="model" type="text" name="website" tabindex="-1" autocomplete="off" /></label>
  </div>
</template>
