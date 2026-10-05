import { computed, ref } from 'vue';
import { parentApi } from '@/services/api';
import { useContextStore } from '@/stores/context';

// Partagé entre les pages de l'espace parent (un seul chargement)
const children = ref([]);
const loading = ref(false);
const error = ref(null);
let loadedAt = 0;

/** Enfants du parent connecté + enfant actuellement sélectionné. */
export function useParentChildren() {
  const ctx = useContextStore();

  async function reload(force = true) {
    if (!force && loadedAt && Date.now() - loadedAt < 60000) return;
    loading.value = true;
    error.value = null;
    try {
      children.value = await parentApi.children();
      loadedAt = Date.now();
    } catch (e) {
      error.value = e;
    } finally {
      loading.value = false;
    }
  }

  if (!loadedAt) reload();
  else reload(false);

  const child = computed(() => children.value.find((c) => c.id === ctx.selectedChildId) ?? children.value[0] ?? null);

  function select(id) {
    ctx.selectedChildId = id;
  }

  return { children, child, loading, error, reload, select };
}

/** À la déconnexion : oublie les enfants chargés. */
export function resetParentChildren() {
  children.value = [];
  loadedAt = 0;
}
