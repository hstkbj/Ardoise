import { defineStore } from 'pinia';
import { reactive, ref } from 'vue';
import { optionsApi } from '@/services/api';
import { OPTIONS } from '@/config/options';

/**
 * Listes de référence chargées depuis l'API (GET /options) et fusionnées dans
 * OPTIONS (objet réactif utilisé par les filtres et formulaires).
 */
export const useOptionsStore = defineStore('options', () => {
  const loaded = ref(false);
  const current = reactive({ academicYear: null, term: null });

  async function load(force = false) {
    if (loaded.value && !force) return;
    const data = await optionsApi.all();
    ['schools', 'academicYears', 'terms', 'levels', 'classes', 'subjects', 'teachers', 'students', 'fees', 'roles'].forEach((key) => {
      OPTIONS[key] = data[key] ?? [];
    });
    current.academicYear = data.currentAcademicYear;
    current.term = data.currentTerm;
    loaded.value = true;
  }

  function reset() {
    loaded.value = false;
  }

  return { loaded, current, load, reset };
});
