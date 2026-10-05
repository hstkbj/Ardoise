<script setup>
/** Notifications de l'utilisateur connecté (administration, enseignant, parent). */
import { computed, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import PageHeader from '@/components/ui/PageHeader.vue';
import AppIcon from '@/components/ui/AppIcon.vue';
import LoadingState from '@/components/ui/LoadingState.vue';
import ErrorState from '@/components/ui/ErrorState.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import Pagination from '@/components/ui/Pagination.vue';
import { notificationsApi } from '@/services/api';
import { useUiStore } from '@/stores/ui';
import { formatDateTime } from '@/utils/format';

const props = defineProps({ compact: { type: Boolean, default: false } });
const ui = useUiStore();
const router = useRouter();

const TYPES = [
  { value: '', label: 'Toutes' },
  { value: 'unread', label: 'Non lues' },
  { value: 'grade', label: 'Notes' },
  { value: 'attendance', label: 'Absences' },
  { value: 'payment', label: 'Paiements' },
  { value: 'report_card', label: 'Bulletins' },
  { value: 'announcement', label: 'Annonces' },
];
const ICONS = { grade: 'pencil', attendance: 'check-circle', payment: 'wallet', report_card: 'file', announcement: 'megaphone', homework: 'list' };

const rows = ref([]);
const meta = ref({ current_page: 1, per_page: 20, total: 0, last_page: 1, unread: 0 });
const loading = ref(true);
const error = ref(null);
const type = ref('');
const page = ref(1);

async function load() {
  loading.value = true;
  error.value = null;
  try {
    const params = { page: page.value, per_page: 20 };
    if (type.value === 'unread') params.unread = 1;
    else if (type.value) params.filter = { type: type.value };
    const res = await notificationsApi.list(params);
    rows.value = res.data;
    meta.value = res.meta;
  } catch (e) {
    error.value = e;
  } finally {
    loading.value = false;
  }
}
watch(type, () => {
  page.value = 1;
  load();
});
watch(page, load);
load();

const unread = computed(() => meta.value.unread ?? rows.value.filter((r) => !r.read).length);

async function open(n) {
  if (!n.read) {
    n.read = true;
    meta.value.unread = Math.max(0, unread.value - 1);
    notificationsApi.read(n.id).catch(() => {});
  }
  if (n.link?.startsWith('/')) router.push(n.link);
}

async function readAll() {
  try {
    await notificationsApi.readAll();
    rows.value = rows.value.map((r) => ({ ...r, read: true }));
    meta.value.unread = 0;
    ui.toast('Toutes les notifications sont marquées comme lues.');
  } catch (e) {
    ui.error(e);
  }
}
</script>

<template>
  <div class="space-y-5" :class="props.compact ? 'mx-auto max-w-2xl' : ''">
    <PageHeader title="Notifications" :subtitle="unread ? `${unread} non lue(s)` : 'Vous êtes à jour.'">
      <template #actions><button type="button" class="btn btn-secondary" :disabled="!unread" @click="readAll"><AppIcon name="check" class="size-4" />Tout marquer comme lu</button></template>
    </PageHeader>

    <div class="flex gap-2 overflow-x-auto pb-1" role="radiogroup" aria-label="Filtrer">
      <button v-for="t in TYPES" :key="t.value" type="button" role="radio" :aria-checked="type === t.value" class="h-8 shrink-0 rounded-full border px-3 text-[13px] font-medium" :class="type === t.value ? 'border-brand-600 bg-brand-600 text-white' : 'border-line bg-white text-body hover:bg-ground'" @click="type = t.value">{{ t.label }}</button>
    </div>

    <div class="card overflow-hidden">
      <LoadingState v-if="loading" variant="table" :rows="6" />
      <ErrorState v-else-if="error" :message="error.message" @retry="load" />
      <EmptyState v-else-if="!rows.length" title="Aucune notification" icon="bell" />
      <ul v-else class="divide-y divide-line-soft">
        <li v-for="n in rows" :key="n.id">
          <button type="button" class="flex w-full gap-3 px-5 py-4 text-left hover:bg-ground-soft" @click="open(n)">
            <span class="flex size-9 shrink-0 items-center justify-center rounded-full" :class="n.read ? 'bg-ground text-subtle' : 'bg-brand-100 text-brand-700'"><AppIcon :name="ICONS[n.type] || 'bell'" class="size-4" /></span>
            <span class="min-w-0 flex-1">
              <span class="flex items-start justify-between gap-3">
                <span class="text-sm" :class="n.read ? 'text-body' : 'font-semibold text-ink'">{{ n.title }}</span>
                <span class="shrink-0 text-xs text-subtle">{{ formatDateTime(n.created_at) }}</span>
              </span>
              <span v-if="n.body" class="mt-0.5 block text-[13px] text-muted">{{ n.body }}</span>
            </span>
            <span v-if="!n.read" class="mt-2 size-2 shrink-0 rounded-full bg-brand-600" aria-label="Non lue" />
          </button>
        </li>
      </ul>
      <Pagination v-if="meta.last_page > 1" v-model="page" :meta="meta" label="notifications" />
    </div>
  </div>
</template>
