<script setup>
/** Fiche élève : informations, parents, bulletin en cours, absences, paiements. */
import { computed, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import PageHeader from '@/components/ui/PageHeader.vue';
import TabNav from '@/components/ui/TabNav.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import StatsCard from '@/components/ui/StatsCard.vue';
import DataTable from '@/components/ui/DataTable.vue';
import Avatar from '@/components/ui/Avatar.vue';
import BaseModal from '@/components/ui/BaseModal.vue';
import LoadingState from '@/components/ui/LoadingState.vue';
import ErrorState from '@/components/ui/ErrorState.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import AppIcon from '@/components/ui/AppIcon.vue';
import ReportCard from '@/components/domain/ReportCard.vue';
import PaymentTable from '@/components/domain/PaymentTable.vue';
import { createResource } from '@/services/resource';
import { attendanceApi, reportCardsApi } from '@/services/api';
import { OPTIONS, optionLabel } from '@/config/options';
import { useAuthStore } from '@/stores/auth';
import { useUiStore } from '@/stores/ui';
import { formatDate, formatScore, openFile } from '@/utils/format';

const props = defineProps({ base: { type: String, default: '/admin/students' } });
const route = useRoute();
const auth = useAuthStore();
const ui = useUiStore();
const api = createResource('students');
const id = route.params.id;

const student = ref(null);
const loading = ref(true);
const error = ref(null);
const tab = ref('overview');

async function load() {
  loading.value = true;
  error.value = null;
  try {
    student.value = await api.get(id);
  } catch (e) {
    error.value = e;
  } finally {
    loading.value = false;
  }
}
load();

// Onglets chargés à la demande
const report = ref(null);
const absences = ref(null);
const payments = ref(null);
const tabLoading = ref(false);
const termId = ref('');

async function loadTab(key) {
  tabLoading.value = true;
  try {
    if (key === 'report') report.value = await reportCardsApi.get(id, { term_id: termId.value || undefined });
    if (key === 'attendance' && !absences.value) absences.value = (await attendanceApi.history({ per_page: 100, filter: { student_id: id } })).data;
    if (key === 'payments' && !payments.value) payments.value = (await createResource('payments').list({ per_page: 100, sort: 'due_date', filter: { student_id: id } })).data;
  } catch (e) {
    ui.error(e);
  } finally {
    tabLoading.value = false;
  }
}
watch(tab, loadTab);
watch(termId, () => loadTab('report'));

const tabs = computed(() => [
  { key: 'overview', label: 'Aperçu' },
  { key: 'report', label: 'Bulletin' },
  { key: 'attendance', label: 'Absences', count: absences.value?.length },
  ...(auth.can('payments.view') ? [{ key: 'payments', label: 'Paiements' }] : []),
]);

const absenceColumns = [
  { key: 'date', label: 'Date', type: 'date' },
  { key: 'slot', label: 'Séance' },
  { key: 'status', label: 'Type', type: 'status' },
  { key: 'minutes', label: 'Retard (min)', type: 'number', align: 'right' },
  { key: 'reason', label: 'Motif' },
];
const balance = computed(() => (payments.value ?? []).reduce((a, p) => a + Number(p.remaining ?? 0), 0));

// Actions de scolarité
const classModal = ref(false);
const newClassId = ref('');
async function runAction(action, payload = {}, confirm = null) {
  if (confirm && !(await ui.confirm(confirm))) return;
  try {
    const out = await api.action(id, action, payload);
    ui.toast(out?.message || 'Action effectuée.');
    classModal.value = false;
    load();
  } catch (e) {
    ui.error(e);
  }
}
</script>

<template>
  <div>
    <LoadingState v-if="loading" variant="detail" />
    <div v-else-if="error" class="card"><ErrorState :message="error.message" @retry="load" /></div>
    <template v-else>
      <PageHeader :title="student.full_name" :breadcrumb="[{ label: 'Élèves', to: base }, { label: student.full_name }]">
        <template #subtitle>
          <span class="inline-flex flex-wrap items-center gap-2">{{ student.matricule }} · {{ student.class_name || 'Sans classe' }} · {{ student.school_name }} <StatusBadge :status="student.status" /></span>
        </template>
        <template v-if="auth.can('students.update')" #actions>
          <button v-if="student.status === 'active'" type="button" class="btn btn-secondary" @click="newClassId = student.class_id; classModal = true"><AppIcon name="layers" class="size-4" />Changer de classe</button>
          <button v-if="student.status === 'active'" type="button" class="btn btn-secondary" @click="runAction('transfer', {}, { title: 'Transférer l’élève ?', message: 'L’inscription en cours sera close avec le statut « transféré ».', confirmLabel: 'Transférer' })">Transférer</button>
          <button v-if="student.status !== 'active'" type="button" class="btn btn-secondary" @click="runAction('restore')">Réactiver</button>
          <RouterLink :to="`${base}/${id}/edit`" class="btn btn-primary"><AppIcon name="edit" class="size-4" />Modifier</RouterLink>
        </template>
      </PageHeader>

      <TabNav v-model="tab" :tabs="tabs" class="mb-5" />

      <div v-if="tab === 'overview'" class="grid gap-5 lg:grid-cols-3">
        <section class="card p-5 lg:col-span-2">
          <h2 class="card-title">Identité</h2>
          <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
            <div><dt class="text-xs text-subtle">Nom</dt><dd>{{ student.last_name }}</dd></div>
            <div><dt class="text-xs text-subtle">Prénoms</dt><dd>{{ student.first_name }}</dd></div>
            <div><dt class="text-xs text-subtle">Sexe</dt><dd>{{ optionLabel('genders', student.gender) || '—' }}</dd></div>
            <div><dt class="text-xs text-subtle">Né(e) le</dt><dd>{{ formatDate(student.birth_date) }}<template v-if="student.birth_place"> à {{ student.birth_place }}</template></dd></div>
            <div><dt class="text-xs text-subtle">Niveau</dt><dd>{{ student.level || '—' }}</dd></div>
            <div><dt class="text-xs text-subtle">Inscrit le</dt><dd>{{ formatDate(student.enrolled_on) }}</dd></div>
          </dl>
        </section>
        <div class="space-y-5">
          <StatsCard label="Moyenne de la période" :value="formatScore(student.average)" unit="/20" />
          <section class="card p-5">
            <h2 class="card-title">Parents / tuteurs</h2>
            <ul v-if="(student.parents ?? []).length" class="mt-3 space-y-3">
              <li v-for="p in student.parents" :key="p.id">
                <RouterLink :to="`/admin/parents/${p.id}`" class="flex items-center gap-3 rounded-lg p-1 hover:bg-ground">
                  <Avatar :name="p.full_name" size="sm" />
                  <span class="min-w-0"><span class="block truncate text-sm font-medium">{{ p.full_name }}</span><span class="text-xs text-subtle">{{ p.relation || 'Parent' }} · {{ p.phone }}</span></span>
                </RouterLink>
              </li>
            </ul>
            <EmptyState v-else compact title="Aucun parent rattaché" icon="users"><RouterLink v-if="auth.can('parents.create')" to="/admin/parents/create" class="btn btn-secondary btn-sm">Ajouter un parent</RouterLink></EmptyState>
          </section>
        </div>
      </div>

      <div v-else-if="tab === 'report'" class="space-y-4">
        <div class="flex flex-wrap items-end justify-between gap-3">
          <div class="w-48"><label for="term" class="label">Période</label><select id="term" v-model="termId" class="input mt-1"><option value="">Période en cours</option><option v-for="t in OPTIONS.terms" :key="t.value" :value="t.value">{{ t.label }}</option></select></div>
          <button v-if="report" type="button" class="btn btn-secondary" @click="openFile(reportCardsApi.pdfPath(id, report.term_id))"><AppIcon name="download" class="size-4" />PDF</button>
        </div>
        <LoadingState v-if="tabLoading" variant="detail" />
        <div v-else-if="report" class="card p-5 sm:p-8"><ReportCard :report="report" /></div>
      </div>

      <div v-else-if="tab === 'attendance'" class="card">
        <DataTable :columns="absenceColumns" :rows="absences ?? []" :loading="tabLoading" empty-title="Aucune absence" empty-text="Aucune absence ni retard enregistré.">
          <template #cell-reason="{ row }"><span v-if="row.justified" class="text-brand-700">Justifiée{{ row.reason ? ` · ${row.reason}` : '' }}</span><span v-else class="text-subtle">Non justifiée</span></template>
        </DataTable>
      </div>

      <div v-else-if="tab === 'payments'" class="space-y-4">
        <StatsCard label="Reste à payer" :value="balance.toLocaleString('fr-FR')" unit="FCFA" :hint-tone="balance ? 'warning' : 'positive'" :hint="balance ? 'Échéances non soldées' : 'À jour'" />
        <div class="card"><PaymentTable :payments="payments ?? []" :loading="tabLoading" :show-student="false" /></div>
      </div>

      <BaseModal :open="classModal" title="Changer de classe" @close="classModal = false">
        <label for="new-class" class="label">Nouvelle classe</label>
        <select id="new-class" v-model="newClassId" class="input mt-1"><option v-for="c in OPTIONS.classes" :key="c.value" :value="c.value">{{ c.label }}</option></select>
        <p class="mt-2 text-xs text-subtle">Les frais de la nouvelle classe sont recalculés automatiquement.</p>
        <template #footer>
          <button type="button" class="btn btn-secondary" @click="classModal = false">Annuler</button>
          <button type="button" class="btn btn-primary" :disabled="!newClassId || newClassId === student.class_id" @click="runAction('change-class', { class_id: newClassId })">Confirmer</button>
        </template>
      </BaseModal>
    </template>
  </div>
</template>
