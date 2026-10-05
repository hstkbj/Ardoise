<script setup>
import { ref, watch } from 'vue';
import ParentSection from '@/components/domain/ParentSection.vue';
import AppIcon from '@/components/ui/AppIcon.vue';
import { parentApi } from '@/services/api';
import { useParentChildren } from '@/composables/useParentChildren';
import { useUiStore } from '@/stores/ui';
import { formatDate, openFile } from '@/utils/format';
import { optionLabel } from '@/config/options';

const ui = useUiStore();
const { child } = useParentChildren();
const docs = ref([]);
const type = ref('Certificat de scolarité');
const sending = ref(false);

watch(() => child.value?.id, async (id) => {
  if (id) docs.value = await parentApi.documents(id).catch(() => []);
}, { immediate: true });

async function request() {
  sending.value = true;
  try {
    const out = await parentApi.requestDocument({ student_id: child.value.id, type: type.value });
    ui.toast(out.message || 'Demande envoyée.');
  } catch (e) {
    ui.error(e);
  } finally {
    sending.value = false;
  }
}
</script>

<template>
  <ParentSection title="Documents">
    <div class="card">
      <p v-if="!docs.length" class="p-6 text-center text-sm text-subtle">Aucun document partagé.</p>
      <ul v-else class="divide-y divide-line-soft">
        <li v-for="d in docs" :key="d.id">
          <button type="button" class="flex w-full items-center gap-3 px-5 py-3.5 text-left hover:bg-ground-soft" @click="openFile(`/documents/${d.id}/download`)">
            <AppIcon name="file" class="size-5 shrink-0 text-brand-600" />
            <span class="min-w-0 flex-1"><span class="block truncate text-sm font-medium">{{ d.name }}</span><span class="text-xs text-subtle">{{ optionLabel('documentCategories', d.category) }} · {{ d.size }} · {{ formatDate(d.uploaded_at) }}</span></span>
            <AppIcon name="download" class="size-4 text-subtle" />
          </button>
        </li>
      </ul>
    </div>
    <form class="card space-y-3 p-5" @submit.prevent="request">
      <h2 class="card-title">Demander un document</h2>
      <select v-model="type" class="input" aria-label="Type de document"><option>Certificat de scolarité</option><option>Attestation de fréquentation</option><option>Duplicata de bulletin</option><option>Attestation de paiement</option></select>
      <button type="submit" class="btn btn-primary w-full" :disabled="sending">Envoyer la demande</button>
    </form>
  </ParentSection>
</template>
