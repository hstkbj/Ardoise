<script setup>
import { ref } from 'vue';
import { useRoute } from 'vue-router';
import ReportCard from '@/components/domain/ReportCard.vue';
import LoadingState from '@/components/ui/LoadingState.vue';
import ErrorState from '@/components/ui/ErrorState.vue';
import AppIcon from '@/components/ui/AppIcon.vue';
import { reportCardsApi } from '@/services/api';
import { openFile } from '@/utils/format';

const route = useRoute();
const studentId = route.params.id;
const termId = route.query.term_id;
const report = ref(null);
const loading = ref(true);
const error = ref(null);

async function load() {
  loading.value = true;
  error.value = null;
  try {
    report.value = await reportCardsApi.get(studentId, { term_id: termId });
  } catch (e) {
    error.value = e;
  } finally {
    loading.value = false;
  }
}
load();
</script>

<template>
  <div class="mx-auto max-w-3xl space-y-4">
    <div class="flex items-center justify-between gap-3 print:hidden">
      <RouterLink to="/parent/report-cards" class="btn btn-ghost -ml-3"><AppIcon name="chevron-left" class="size-4" />Bulletins</RouterLink>
      <button v-if="report" type="button" class="btn btn-primary" @click="openFile(reportCardsApi.pdfPath(studentId, report.term_id))"><AppIcon name="download" class="size-4" />Télécharger le PDF</button>
    </div>
    <LoadingState v-if="loading" variant="detail" />
    <div v-else-if="error" class="card"><ErrorState :message="error.message" @retry="load" /></div>
    <div v-else class="card overflow-x-auto p-4 sm:p-8"><ReportCard :report="report" /></div>
  </div>
</template>
