import { ref } from 'vue';

/**
 * États loading / error / data d'un appel asynchrone.
 *   const { data, loading, error, run } = useAsync(() => api.get(id), { immediate: true })
 */
export function useAsync(fn, { immediate = false, initial = null } = {}) {
  const data = ref(initial);
  const loading = ref(immediate);
  const error = ref(null);

  async function run(...args) {
    loading.value = true;
    error.value = null;
    try {
      data.value = await fn(...args);
      return data.value;
    } catch (e) {
      error.value = e?.message ? e : { message: 'Une erreur est survenue.' };
      return null;
    } finally {
      loading.value = false;
    }
  }

  if (immediate) run();

  return { data, loading, error, run };
}
