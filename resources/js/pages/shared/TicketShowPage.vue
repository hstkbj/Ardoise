<script setup>
/** Fil d'un ticket de support — côté école (support-tickets) ou plateforme (platform/tickets). */
import { computed, ref } from 'vue';
import { useRoute } from 'vue-router';
import PageHeader from '@/components/ui/PageHeader.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import LoadingState from '@/components/ui/LoadingState.vue';
import ErrorState from '@/components/ui/ErrorState.vue';
import AppIcon from '@/components/ui/AppIcon.vue';
import http from '@/services/http';
import { supportApi } from '@/services/api';
import { useUiStore } from '@/stores/ui';
import { optionLabel } from '@/config/options';
import { formatDateTime } from '@/utils/format';

const props = defineProps({ base: { type: String, default: 'support-tickets' }, listPath: { type: String, default: '/admin/support' } });
const route = useRoute();
const ui = useUiStore();
const id = route.params.id;
const isPlatform = computed(() => props.base.startsWith('platform'));

const ticket = ref(null);
const messages = ref([]);
const loading = ref(true);
const error = ref(null);
const reply = ref('');
const sending = ref(false);

async function load() {
  loading.value = true;
  error.value = null;
  try {
    const [t, m] = await Promise.all([http.get(`/${props.base}/${id}`).then((r) => r.data.data), supportApi.thread(props.base, id)]);
    ticket.value = t;
    messages.value = m;
  } catch (e) {
    error.value = e;
  } finally {
    loading.value = false;
  }
}
load();

const mine = (m) => (isPlatform.value ? m.side === 'support' : m.side === 'client');

async function send() {
  if (!reply.value.trim()) return;
  sending.value = true;
  try {
    messages.value.push(await supportApi.reply(props.base, id, reply.value.trim()));
    reply.value = '';
  } catch (e) {
    ui.error(e);
  } finally {
    sending.value = false;
  }
}

async function setStatus(status) {
  try {
    ticket.value = await supportApi.setStatus(id, status);
    ui.toast('Statut mis à jour.');
  } catch (e) {
    ui.error(e);
  }
}
</script>

<template>
  <div class="mx-auto max-w-3xl space-y-5">
    <LoadingState v-if="loading" variant="cards" :rows="3" />
    <div v-else-if="error" class="card"><ErrorState :message="error.message" @retry="load" /></div>
    <template v-else>
      <PageHeader :title="ticket.subject" :breadcrumb="[{ label: 'Support', to: listPath }, { label: `Ticket n° ${ticket.id}` }]">
        <template #subtitle>
          <span class="inline-flex flex-wrap items-center gap-2">
            <StatusBadge :status="ticket.status" />
            <span v-if="ticket.priority">Priorité {{ String(optionLabel('priorities', ticket.priority)).toLowerCase() }}</span>
            <span v-if="ticket.category">· {{ ticket.category }}</span>
            <span v-if="isPlatform && ticket.tenant_name">· {{ ticket.tenant_name }} ({{ ticket.requester }})</span>
          </span>
        </template>
        <template v-if="isPlatform" #actions>
          <button v-if="ticket.status !== 'in_progress'" type="button" class="btn btn-secondary" @click="setStatus('in_progress')">Prendre en charge</button>
          <button v-if="ticket.status !== 'resolved'" type="button" class="btn btn-primary" @click="setStatus('resolved')"><AppIcon name="check" class="size-4" />Résolu</button>
        </template>
      </PageHeader>

      <ol class="space-y-3">
        <li v-for="m in messages" :key="m.id" class="flex" :class="mine(m) ? 'justify-end' : ''">
          <div class="max-w-[85%] rounded-2xl px-4 py-3" :class="mine(m) ? 'bg-brand-600 text-white' : 'border border-line bg-white'">
            <p class="text-xs" :class="mine(m) ? 'text-brand-100' : 'text-subtle'">{{ m.author }} · {{ formatDateTime(m.at) }}</p>
            <p class="mt-1 text-sm whitespace-pre-line">{{ m.body }}</p>
          </div>
        </li>
      </ol>

      <form class="card space-y-3 p-4" @submit.prevent="send">
        <label for="reply" class="label">Votre réponse</label>
        <textarea id="reply" v-model="reply" rows="4" class="input" />
        <div class="flex justify-end"><button type="submit" class="btn btn-primary" :disabled="sending || !reply.trim()"><AppIcon name="send" class="size-4" />Envoyer</button></div>
      </form>
    </template>
  </div>
</template>
