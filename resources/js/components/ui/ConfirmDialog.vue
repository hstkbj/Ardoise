<script setup>
import { computed } from 'vue';
import { useUiStore } from '@/stores/ui';
import BaseModal from './BaseModal.vue';

const ui = useUiStore();
const state = computed(() => ui.confirmState);
</script>

<template>
  <BaseModal :open="!!state" :title="state?.title" size="sm" @close="ui.resolveConfirm(false)">
    <p class="text-sm leading-relaxed text-body">{{ state?.message }}</p>
    <template #footer>
      <button type="button" class="btn btn-secondary" @click="ui.resolveConfirm(false)">{{ state?.cancelLabel }}</button>
      <button type="button" class="btn" :class="state?.danger ? 'btn-danger' : 'btn-primary'" @click="ui.resolveConfirm(true)">{{ state?.confirmLabel }}</button>
    </template>
  </BaseModal>
</template>
