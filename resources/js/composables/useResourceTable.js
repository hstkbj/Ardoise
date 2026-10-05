import { reactive, ref, watch, computed } from 'vue';
import { createResource } from '@/services/resource';

/**
 * Liste paginée + recherche + filtres + tri + sélection multiple.
 *   ?search=&page=&per_page=&sort=-date&filter[status]=active
 */
export function useResourceTable(endpoint, { perPage = 15, initialFilters = {}, initialSort = '' } = {}) {
  const api = createResource(endpoint);

  const rows = ref([]);
  const meta = ref({ current_page: 1, per_page: perPage, total: 0, last_page: 1 });
  const loading = ref(true);
  const error = ref(null);

  const search = ref('');
  const page = ref(1);
  const sort = ref(initialSort);
  const filters = reactive({ ...initialFilters });
  const selected = ref([]);

  let requestId = 0;
  let searchTimer;

  async function load() {
    const id = ++requestId;
    loading.value = true;
    error.value = null;
    try {
      const res = await api.list({
        search: search.value || undefined,
        page: page.value,
        per_page: perPage,
        sort: sort.value || undefined,
        filter: Object.fromEntries(Object.entries(filters).filter(([, v]) => v !== '' && v !== null && v !== undefined)),
      });
      if (id !== requestId) return;
      rows.value = res.data ?? [];
      meta.value = res.meta ?? { current_page: 1, per_page: rows.value.length, total: rows.value.length, last_page: 1 };
    } catch (e) {
      if (id !== requestId) return;
      error.value = e;
      rows.value = [];
    } finally {
      if (id === requestId) loading.value = false;
    }
  }

  function toggleSort(key) {
    if (sort.value === key) sort.value = `-${key}`;
    else if (sort.value === `-${key}`) sort.value = '';
    else sort.value = key;
  }

  function resetFilters() {
    Object.keys(filters).forEach((k) => (filters[k] = ''));
    search.value = '';
  }

  const hasActiveFilters = computed(() => !!search.value || Object.values(filters).some((v) => v !== '' && v != null));

  watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
      page.value = 1;
      load();
    }, 300);
  });
  watch(
    () => ({ ...filters }),
    () => {
      page.value = 1;
      load();
    },
  );
  watch([page, sort], load);

  load();

  return { api, rows, meta, loading, error, search, page, sort, filters, selected, load, toggleSort, resetFilters, hasActiveFilters };
}
