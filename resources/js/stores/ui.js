import { defineStore } from 'pinia';
import { ref } from 'vue';

/** Toasts et boîte de confirmation globales. */
export const useUiStore = defineStore('ui', () => {
  const toasts = ref([]);
  const confirmState = ref(null);
  let seq = 0;

  function toast(message, { tone = 'success', title = null, timeout = 4500 } = {}) {
    const id = ++seq;
    toasts.value.push({ id, message, tone, title });
    if (timeout) setTimeout(() => dismiss(id), timeout);
    return id;
  }

  function error(e) {
    return toast(e?.message || 'Une erreur est survenue.', { tone: 'error', timeout: 6000 });
  }

  function dismiss(id) {
    toasts.value = toasts.value.filter((t) => t.id !== id);
  }

  /** const ok = await ui.confirm({ title, message, confirmLabel, danger: true }) */
  function confirm(options) {
    return new Promise((resolve) => {
      confirmState.value = { title: 'Confirmer', message: '', confirmLabel: 'Confirmer', cancelLabel: 'Annuler', danger: false, ...options, resolve };
    });
  }

  function resolveConfirm(value) {
    confirmState.value?.resolve(value);
    confirmState.value = null;
  }

  return { toasts, toast, error, dismiss, confirmState, confirm, resolveConfirm };
});
