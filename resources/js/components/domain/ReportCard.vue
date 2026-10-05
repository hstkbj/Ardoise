<script setup>
/** Bulletin scolaire (affichage + impression), au format renvoyé par GET /report-cards/{student}. */
import { computed } from 'vue';
import { formatDate } from '@/utils/format';

const props = defineProps({ report: { type: Object, required: true } });

const fmt = (v) => (v == null ? '—' : Number(v).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
const totalCoef = computed(() => props.report.subjects.reduce((a, s) => a + Number(s.coefficient), 0));
const totalPoints = computed(() => props.report.subjects.reduce((a, s) => a + (s.average ?? 0) * s.coefficient, 0));
</script>

<template>
  <article class="mx-auto max-w-[820px] rounded-xl border border-line bg-white p-6 text-[13px] sm:p-10 print:border-0 print:p-0">
    <p v-if="!report.generated" class="mb-4 rounded-lg bg-warn-50 px-4 py-2.5 text-[13px] text-warn-700 print:hidden">Aperçu provisoire : ce bulletin n’a pas encore été généré.</p>
    <header class="flex flex-wrap items-start justify-between gap-4 border-b border-ink pb-4">
      <div><p class="text-base font-bold">{{ report.school }}</p><p class="text-muted">Année scolaire {{ report.academic_year }}</p></div>
      <div class="text-right"><p class="text-lg font-bold tracking-tight uppercase">Bulletin de notes</p><p class="text-muted">{{ report.term }}</p></div>
    </header>

    <dl class="grid grid-cols-2 gap-x-6 gap-y-2 py-4 sm:grid-cols-4">
      <div><dt class="text-xs text-subtle">Élève</dt><dd class="font-semibold">{{ report.student.full_name }}</dd></div>
      <div><dt class="text-xs text-subtle">Matricule</dt><dd>{{ report.student.matricule }}</dd></div>
      <div><dt class="text-xs text-subtle">Classe</dt><dd>{{ report.student.class_name }} · {{ report.class_size }} élèves</dd></div>
      <div><dt class="text-xs text-subtle">Né(e) le</dt><dd>{{ formatDate(report.student.birth_date) }}</dd></div>
    </dl>

    <div class="overflow-x-auto">
      <table class="w-full min-w-[640px] border-collapse">
        <thead>
          <tr class="bg-ground text-left text-xs">
            <th scope="col" class="border border-line px-2 py-2 font-semibold">Matière</th>
            <th scope="col" class="border border-line px-2 py-2 text-right font-semibold">Coef.</th>
            <th scope="col" class="border border-line px-2 py-2 text-right font-semibold">Moyenne</th>
            <th scope="col" class="border border-line px-2 py-2 text-right font-semibold">Moy. × coef.</th>
            <th scope="col" class="border border-line px-2 py-2 text-right font-semibold">Moy. classe</th>
            <th scope="col" class="border border-line px-2 py-2 font-semibold">Appréciation</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="s in report.subjects" :key="s.name">
            <td class="border border-line px-2 py-1.5"><span class="font-medium">{{ s.name }}</span><br /><span class="text-xs text-subtle">{{ s.teacher }}</span></td>
            <td class="border border-line px-2 py-1.5 text-right tabular">{{ Number(s.coefficient).toLocaleString('fr-FR') }}</td>
            <td class="border border-line px-2 py-1.5 text-right font-semibold tabular" :class="s.average != null && s.average < 10 ? 'text-danger-600' : ''">{{ fmt(s.average) }}</td>
            <td class="border border-line px-2 py-1.5 text-right tabular">{{ s.average == null ? '—' : fmt(s.average * s.coefficient) }}</td>
            <td class="border border-line px-2 py-1.5 text-right tabular text-muted">{{ fmt(s.class_average) }}</td>
            <td class="border border-line px-2 py-1.5">{{ s.appreciation }}</td>
          </tr>
          <tr v-if="!report.subjects.length"><td colspan="6" class="border border-line px-2 py-6 text-center text-muted">Aucune note validée sur cette période.</td></tr>
        </tbody>
        <tfoot>
          <tr class="bg-ground font-semibold">
            <td class="border border-line px-2 py-2">Total</td>
            <td class="border border-line px-2 py-2 text-right tabular">{{ totalCoef.toLocaleString('fr-FR') }}</td>
            <td class="border border-line px-2 py-2" />
            <td class="border border-line px-2 py-2 text-right tabular">{{ fmt(totalPoints) }}</td>
            <td class="border border-line px-2 py-2" colspan="2" />
          </tr>
        </tfoot>
      </table>
    </div>

    <section class="mt-6 grid gap-4 sm:grid-cols-3">
      <div class="rounded-lg border border-line p-4">
        <p class="text-xs text-subtle">Moyenne générale</p>
        <p class="mt-1 text-2xl font-bold tabular">{{ fmt(report.general_average) }}<span class="text-sm font-medium text-subtle"> /20</span></p>
        <p class="text-xs text-muted">Moyenne de la classe : {{ fmt(report.class_average) }}</p>
      </div>
      <div class="rounded-lg border border-line p-4">
        <p class="text-xs text-subtle">Rang</p>
        <p class="mt-1 text-2xl font-bold tabular"><template v-if="report.show_rank !== false && report.rank">{{ report.rank }}<sup>{{ report.rank === 1 ? 'er' : 'e' }}</sup></template><template v-else>—</template><span class="text-sm font-medium text-subtle"> / {{ report.class_size }}</span></p>
      </div>
      <div class="rounded-lg border border-line p-4">
        <p class="text-xs text-subtle">Assiduité</p>
        <p class="mt-1 text-base font-semibold">{{ report.absences }} séance(s) manquée(s)</p>
        <p class="text-xs text-muted">{{ report.late }} retard(s)</p>
      </div>
    </section>

    <section class="mt-4 grid gap-4 sm:grid-cols-2">
      <div class="rounded-lg border border-line p-4"><p class="text-xs text-subtle">Appréciation du professeur principal</p><p class="mt-1">{{ report.head_teacher_comment || '—' }}</p></div>
      <div class="rounded-lg border border-line p-4"><p class="text-xs text-subtle">Décision du conseil de classe</p><p class="mt-1 font-semibold">{{ report.council_decision || '—' }}</p></div>
    </section>

    <footer class="mt-10 grid grid-cols-2 gap-6 text-xs text-muted">
      <div>Le professeur principal<div class="mt-10 border-t border-line-strong" /></div>
      <div>Le chef d'établissement<div class="mt-10 border-t border-line-strong" /></div>
    </footer>
  </article>
</template>
