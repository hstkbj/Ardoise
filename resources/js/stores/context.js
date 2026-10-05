import { defineStore } from 'pinia';
import { ref } from 'vue';

/** Contexte de travail : établissement courant (barre du haut) et enfant affiché (espace parent). */
export const useContextStore = defineStore('context', () => {
  const schoolId = ref('');
  const selectedChildId = ref(null);

  return { schoolId, selectedChildId };
});
