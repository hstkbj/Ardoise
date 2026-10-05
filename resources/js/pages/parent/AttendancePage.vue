<script setup>
import { ref } from 'vue';
import ParentSection from '@/components/domain/ParentSection.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import BaseModal from '@/components/ui/BaseModal.vue';
import { parentApi } from '@/services/api';
import { useUiStore } from '@/stores/ui';
import { formatDate } from '@/utils/format';

const ui = useUiStore();
const target = ref(null);
const reason = ref('');
const file = ref(null);
const sending = ref(false);
let reloadFn = null;

function openJustify(a, reload) {
  target.value = a;
  reason.value = '';
  file.value = null;
  reloadFn = reload;
}

async function send() {
  if (!reason.value.trim()) return ui.toast('Indiquez le motif.', { tone: 'error' });
  sending.value = true;
  try {
    await parentApi.justify(target.value.id, { reason: reason.value.trim(), file: file.value || undefined });
    ui.toast('Justificatif envoyé à l’établissement.');
    target.value = null;
    reloadFn?.();
  } catch (e) {
    ui.error(e);
  } finally {
    sending.value = false;
  }
}
</script>

<template>
  <ParentSection title="Absences et retards">
    <template #default="{ child, reload }">
      <div class="grid grid-cols-2 gap-3">
        <div class="rounded-2xl bg-blush p-4"><p class="text-xs text-muted">Absences ({{ child.term || 'période' }})</p><p class="text-2xl font-semibold tabular">{{ child.absences }}</p></div>
        <div class="rounded-2xl bg-sky p-4"><p class="text-xs text-muted">Retards</p><p class="text-2xl font-semibold tabular">{{ child.late }}</p></div>
      </div>
      <div class="card">
        <p v-if="!child.attendance.length" class="p-6 text-center text-sm text-subtle">Aucune absence ni retard. Bravo !</p>
        <ul v-else class="divide-y divide-line-soft">
          <li v-for="a in child.attendance" :key="a.id" class="flex flex-wrap items-center gap-3 px-5 py-3.5">
            <span class="min-w-0 flex-1">
              <span class="block text-sm font-medium">{{ formatDate(a.date, { weekday: 'long', day: 'numeric', month: 'long' }) }} · {{ a.slot }}</span>
              <span class="text-xs text-subtle">{{ a.type === 'late' ? `Retard de ${a.minutes || '?'} min` : 'Absence' }}<template v-if="a.reason"> · {{ a.reason }}</template></span>
            </span>
            <StatusBadge v-if="a.justified" status="excused" />
            <button v-else-if="a.reason" type="button" class="text-xs text-muted" disabled>Justificatif en attente</button>
            <button v-else type="button" class="btn btn-secondary btn-sm" @click="openJustify(a, reload)">Justifier</button>
          </li>
        </ul>
      </div>
    </template>
  </ParentSection>

  <BaseModal :open="!!target" title="Justifier" :description="target ? formatDate(target.date) : ''" @close="target = null">
    <div class="space-y-4">
      <div class="flex flex-col gap-1"><label for="j-reason" class="label">Motif *</label><textarea id="j-reason" v-model="reason" rows="3" maxlength="255" class="input" placeholder="Maladie, rendez-vous médical…" /></div>
      <div class="flex flex-col gap-1"><label for="j-file" class="label">Justificatif (photo ou PDF)</label><input id="j-file" type="file" accept="image/*,application/pdf" class="text-sm" @change="file = $event.target.files[0] || null" /></div>
    </div>
    <template #footer><button type="button" class="btn btn-secondary" @click="target = null">Annuler</button><button type="button" class="btn btn-primary" :disabled="sending" @click="send">Envoyer</button></template>
  </BaseModal>
</template>
