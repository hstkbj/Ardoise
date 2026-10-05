<script setup>
/** Saisie des notes d'une évaluation (administration et enseignants). */
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter, onBeforeRouteLeave } from 'vue-router';
import PageHeader from '@/components/ui/PageHeader.vue';
import FilterDropdown from '@/components/ui/FilterDropdown.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import StatsCard from '@/components/ui/StatsCard.vue';
import LoadingState from '@/components/ui/LoadingState.vue';
import ErrorState from '@/components/ui/ErrorState.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import AppIcon from '@/components/ui/AppIcon.vue';
import GradeTable from '@/components/domain/GradeTable.vue';
import { gradesApi } from '@/services/api';
import { createResource } from '@/services/resource';
import { OPTIONS } from '@/config/options';
import { useAuthStore, STAFF_ROLES } from '@/stores/auth';
import { useOptionsStore } from '@/stores/options';
import { useUiStore } from '@/stores/ui';
import { formatDate, formatScore, parseGrade } from '@/utils/format';

const props = defineProps({ assessmentsBase: { type: String, default: '/admin/assessments' } });
const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const ui = useUiStore();
const optionsStore = useOptionsStore();

const classId = ref(route.query.class_id ?? '');
const termId = ref(route.query.term_id ?? optionsStore.current.term ?? '');
const assessmentId = ref(route.query.assessment ? Number(route.query.assessment) : '');
const assessments = ref([]);
const loadingList = ref(false);

const assessment = ref(null);
const rows = ref([]);
const loading = ref(false);
const error = ref(null);
const dirty = ref(false);
const saving = ref(false);

async function loadAssessments() {
  loadingList.value = true;
  try {
    const res = await createResource('assessments').list({ per_page: 100, sort: '-date', filter: { class_id: classId.value || undefined, term_id: termId.value || undefined } });
    assessments.value = res.data;
  } catch (e) {
    ui.error(e);
  } finally {
    loadingList.value = false;
  }
}

async function loadSheet() {
  if (!assessmentId.value) {
    assessment.value = null;
    rows.value = [];
    return;
  }
  loading.value = true;
  error.value = null;
  try {
    const res = await gradesApi.sheet(assessmentId.value);
    assessment.value = res.assessment;
    rows.value = res.data;
    dirty.value = false;
  } catch (e) {
    error.value = e;
  } finally {
    loading.value = false;
  }
}

watch([classId, termId], loadAssessments);
watch(assessmentId, (id) => {
  router.replace({ query: { ...route.query, assessment: id || undefined } });
  loadSheet();
});
loadAssessments();
loadSheet();

const assessmentOptions = computed(() => assessments.value.map((a) => ({ value: a.id, label: `${a.title} · ${a.class_name} · ${a.subject_name} (${formatDate(a.date)})` })));
const locked = computed(() => assessment.value?.status === 'validated');

const stats = computed(() => {
  const max = Number(assessment.value?.max_score || 20);
  const scores = rows.value.filter((r) => !r.absent).map((r) => parseGrade(r.score)).filter((n) => n !== null && !Number.isNaN(n) && n >= 0 && n <= max);
  const avg = scores.length ? scores.reduce((a, b) => a + b, 0) / scores.length : null;
  return {
    entered: scores.length + rows.value.filter((r) => r.absent).length,
    avg,
    min: scores.length ? Math.min(...scores) : null,
    max: scores.length ? Math.max(...scores) : null,
    invalid: rows.value.some((r) => !r.absent && Number.isNaN(parseGrade(r.score) ?? 0)) || rows.value.some((r) => { const n = parseGrade(r.score); return n !== null && (n < 0 || n > max); }),
  };
});

function payload() {
  return rows.value.map((r) => ({ student_id: r.student_id, score: r.absent ? null : parseGrade(r.score), absent: !!r.absent, comment: r.comment || null }));
}

async function save() {
  if (stats.value.invalid) return ui.toast(`Certaines notes sont invalides (entre 0 et ${assessment.value.max_score}).`, { tone: 'error' });
  saving.value = true;
  try {
    await gradesApi.save(assessmentId.value, payload());
    dirty.value = false;
    ui.toast('Notes enregistrées.');
  } catch (e) {
    ui.error(e);
  } finally {
    saving.value = false;
  }
}

async function validate() {
  if (stats.value.invalid) return ui.toast('Corrigez les notes invalides avant de valider.', { tone: 'error' });
  if (stats.value.entered < rows.value.length) return ui.toast('Chaque élève doit avoir une note ou être marqué absent.', { tone: 'error' });
  const ok = await ui.confirm({ title: 'Valider les notes ?', message: 'Les notes seront verrouillées et visibles par les parents. Seule la direction pourra les déverrouiller.', confirmLabel: 'Valider' });
  if (!ok) return;
  saving.value = true;
  try {
    assessment.value = await gradesApi.validate(assessmentId.value, payload());
    dirty.value = false;
    ui.toast('Notes validées et publiées.');
  } catch (e) {
    ui.error(e);
  } finally {
    saving.value = false;
  }
}

async function unlock() {
  const ok = await ui.confirm({ title: 'Déverrouiller les notes ?', message: 'Les notes redeviennent modifiables et sont masquées aux parents jusqu’à la prochaine validation. L’opération est journalisée.', confirmLabel: 'Déverrouiller', danger: true });
  if (!ok) return;
  try {
    assessment.value = await gradesApi.unlock(assessmentId.value, 'Correction');
    ui.toast('Évaluation déverrouillée.');
  } catch (e) {
    ui.error(e);
  }
}

const canUnlock = computed(() => locked.value && auth.can('grades.publish') && auth.hasRole(...STAFF_ROLES));

onBeforeRouteLeave(async () => {
  if (!dirty.value) return true;
  return ui.confirm({ title: 'Quitter sans enregistrer ?', message: 'Les notes saisies depuis le dernier enregistrement seront perdues.', confirmLabel: 'Quitter', danger: true });
});
</script>

<template>
  <div class="space-y-5">
    <PageHeader title="Saisie des notes" subtitle="Entrée ou ↓ pour passer à l’élève suivant. Virgule ou point acceptés.">
      <template #actions><RouterLink :to="`${assessmentsBase}/create`" class="btn btn-secondary"><AppIcon name="plus" class="size-4" />Nouvelle évaluation</RouterLink></template>
    </PageHeader>

    <div class="card grid gap-3 p-4 sm:grid-cols-[180px_180px_1fr]">
      <FilterDropdown v-model="classId" label="Classe" :options="OPTIONS.classes" all-label="Toutes les classes" />
      <FilterDropdown v-model="termId" label="Période" :options="OPTIONS.terms" all-label="Toutes" />
      <FilterDropdown v-model="assessmentId" label="Évaluation" :options="assessmentOptions" :all-label="loadingList ? 'Chargement…' : 'Choisir une évaluation'" />
    </div>

    <div v-if="!assessmentId" class="card"><EmptyState title="Choisissez une évaluation" description="Filtrez par classe et période, puis sélectionnez l’évaluation à noter." icon="pencil" /></div>
    <LoadingState v-else-if="loading" />
    <div v-else-if="error" class="card"><ErrorState :message="error.message" @retry="loadSheet" /></div>
    <template v-else-if="assessment">
      <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <StatsCard label="Notes saisies" :value="`${stats.entered} / ${rows.length}`" />
        <StatsCard label="Moyenne" :value="formatScore(stats.avg)" :unit="`/${assessment.max_score}`" />
        <StatsCard label="Note la plus basse" :value="formatScore(stats.min)" />
        <StatsCard label="Note la plus haute" :value="formatScore(stats.max)" />
      </div>

      <section class="card">
        <div class="flex flex-wrap items-center gap-3 border-b border-line-soft px-5 py-4">
          <div class="min-w-0 flex-1">
            <h2 class="card-title">{{ assessment.title }}</h2>
            <p class="text-sm text-muted">{{ assessment.class_name }} · {{ assessment.subject_name }} · {{ formatDate(assessment.date) }} · coef. {{ assessment.coefficient }} · sur {{ assessment.max_score }}</p>
          </div>
          <StatusBadge :status="assessment.status" />
        </div>
        <p v-if="locked" class="flex items-center gap-2 bg-info-50 px-5 py-2.5 text-[13px] text-info-700"><AppIcon name="lock" class="size-4" />Notes validées et visibles par les parents.</p>
        <EmptyState v-if="!rows.length" title="Aucun élève dans cette classe" icon="users" />
        <GradeTable v-else v-model:rows="rows" :max-score="Number(assessment.max_score)" :coefficient="Number(assessment.coefficient)" :locked="locked" @change="dirty = true" />
        <div class="flex flex-wrap justify-end gap-2 border-t border-line-soft px-5 py-4">
          <template v-if="!locked">
            <button type="button" class="btn btn-secondary" :disabled="saving" @click="save">Enregistrer le brouillon</button>
            <button type="button" class="btn btn-primary" :disabled="saving" @click="validate"><AppIcon name="check" class="size-4" />Valider et publier</button>
          </template>
          <button v-else-if="canUnlock" type="button" class="btn btn-secondary" @click="unlock"><AppIcon name="unlock" class="size-4" />Déverrouiller</button>
        </div>
      </section>
    </template>
  </div>
</template>
