<script setup>
/** Bulletin d'un élève : aperçu imprimable, appréciation, décision, PDF. */
import { reactive, ref } from 'vue';
import { useRoute } from 'vue-router';
import PageHeader from '@/components/ui/PageHeader.vue';
import LoadingState from '@/components/ui/LoadingState.vue';
import ErrorState from '@/components/ui/ErrorState.vue';
import AppIcon from '@/components/ui/AppIcon.vue';
import ReportCard from '@/components/domain/ReportCard.vue';
import { reportCardsApi } from '@/services/api';
import { useAuthStore } from '@/stores/auth';
import { useUiStore } from '@/stores/ui';
import { openFile } from '@/utils/format';

const props = defineProps({ backPath: { type: String, default: '/admin/report-cards' } });
const route = useRoute();
const auth = useAuthStore();
const ui = useUiStore();
const studentId = route.params.id;
const termId = route.query.term_id;

const report = ref(null);
const loading = ref(true);
const error = ref(null);
const form = reactive({ head_teacher_comment: '', council_decision: '' });
const saving = ref(false);

async function load() {
  loading.value = true;
  error.value = null;
  try {
    report.value = await reportCardsApi.get(studentId, { term_id: termId });
    form.head_teacher_comment = report.value.head_teacher_comment ?? '';
    form.council_decision = report.value.council_decision ?? '';
  } catch (e) {
    error.value = e;
  } finally {
    loading.value = false;
  }
}
load();
const print = () => window.print();

async function save() {
  saving.value = true;
  try {
    report.value = await reportCardsApi.update(studentId, { term_id: report.value.term_id, ...form });
    ui.toast('Bulletin mis à jour.');
  } catch (e) {
    ui.error(e);
  } finally {
    saving.value = false;
  }
}
</script>

<template>
  <div>
    <LoadingState v-if="loading" variant="detail" />
    <div v-else-if="error" class="card"><ErrorState :message="error.message" @retry="load" /></div>
    <template v-else>
      <PageHeader :title="`Bulletin · ${report.student.full_name}`" :subtitle="report.term" :breadcrumb="[{ label: 'Bulletins', to: backPath }, { label: report.student.full_name }]">
        <template #actions>
          <button type="button" class="btn btn-secondary" @click="print"><AppIcon name="printer" class="size-4" />Imprimer</button>
          <button type="button" class="btn btn-primary" @click="openFile(reportCardsApi.pdfPath(studentId, report.term_id))"><AppIcon name="download" class="size-4" />PDF</button>
        </template>
      </PageHeader>
      <div class="grid gap-5 xl:grid-cols-[1fr_320px]">
        <div class="card p-5 sm:p-8 print:border-0 print:p-0"><ReportCard :report="report" /></div>
        <form v-if="report.generated && auth.can('report_cards.update')" class="card h-fit space-y-4 p-5 print:hidden" @submit.prevent="save">
          <h2 class="card-title">Conseil de classe</h2>
          <div class="flex flex-col gap-1"><label for="rc-comment" class="label">Appréciation du professeur principal</label><textarea id="rc-comment" v-model="form.head_teacher_comment" rows="4" maxlength="255" class="input" /></div>
          <div class="flex flex-col gap-1"><label for="rc-decision" class="label">Décision</label><input id="rc-decision" v-model="form.council_decision" maxlength="100" class="input" placeholder="Admis(e) en classe supérieure…" /></div>
          <button type="submit" class="btn btn-primary w-full" :disabled="saving">Enregistrer</button>
        </form>
      </div>
    </template>
  </div>
</template>
