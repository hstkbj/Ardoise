<script setup>
/**
 * Guide de mise en route : un seul classeur Excel à plusieurs feuilles (enseignants, matières, classes,
 * affectations, élèves, parents). Les liens manquants sont créés ou complétés automatiquement.
 */
import { computed, ref } from 'vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import ErrorState from '@/components/ui/ErrorState.vue';
import AppIcon from '@/components/ui/AppIcon.vue';
import ImportDialog from '@/components/domain/ImportDialog.vue';
import { importsApi } from '@/services/api';
import { useAsync } from '@/composables/useAsync';
import { formatNumber, openFile } from '@/utils/format';

const { data: status, loading, error, run } = useAsync(() => importsApi.setupStatus(), { immediate: true });
const importOpen = ref(false);
const last = ref(null);

const items = computed(() => [
  { key: 'schools', label: 'Établissements', to: '/admin/schools' },
  { key: 'teachers', label: 'Enseignants', to: '/admin/teachers' },
  { key: 'subjects', label: 'Matières', to: '/admin/subjects' },
  { key: 'classes', label: 'Classes', to: '/admin/classes' },
  { key: 'students', label: 'Élèves', to: '/admin/students' },
  { key: 'parents', label: 'Parents', to: '/admin/parents' },
]);

const SHEETS = [
  ['Enseignants', 'Nom, prénoms, e-mail, téléphone, établissements, matières.'],
  ['Matières', 'Nom, code, catégorie, coefficient, enseignant.'],
  ['Classes', 'Nom, niveau, établissement (créé s’il n’existe pas), capacité, professeur principal (créé s’il n’existe pas).'],
  ['Affectations', 'Quelle matière, quel enseignant et quel coefficient dans chaque classe.'],
  ['Élèves', 'Identité, classe et premier parent (retrouvé par téléphone, sinon créé avec son code d’accès).'],
  ['Parents', 'Un parent lié à un ou plusieurs élèves par leur matricule.'],
];

function imported(result) {
  last.value = result;
  run();
}
</script>

<template>
  <div class="space-y-6">
    <PageHeader title="Mise en route de l’école" subtitle="Enregistrez vos données d’un coup avec un seul fichier Excel." />

    <div v-if="error" class="card"><ErrorState :message="error.message" @retry="run" /></div>
    <template v-else>
      <div v-if="status && !status.has_open_year" class="card flex flex-wrap items-center gap-3 border-warn-200 bg-warn-50 p-4 text-sm">
        <AppIcon name="calendar" class="size-5 shrink-0 text-warn-700" />
        <p class="min-w-0 flex-1">Aucune année scolaire n’est ouverte : créez-la d’abord pour pouvoir inscrire des élèves.</p>
        <RouterLink to="/admin/academic-years/create" class="btn btn-sm btn-secondary">Créer l’année scolaire</RouterLink>
      </div>

      <section class="card p-5 sm:p-6">
        <h2 class="mb-4 font-semibold">Où en êtes-vous ?</h2>
        <ul class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          <li v-for="i in items" :key="i.key">
            <RouterLink :to="i.to" class="flex items-center gap-3 rounded-xl border border-line-soft p-3 hover:bg-ground">
              <span class="flex size-8 items-center justify-center rounded-full" :class="status?.[i.key] > 0 ? 'bg-mint text-brand-700' : 'bg-ground text-muted'">
                <AppIcon :name="status?.[i.key] > 0 ? 'check-circle' : 'plus'" class="size-4" />
              </span>
              <span class="flex-1 text-sm font-medium">{{ i.label }}</span>
              <span class="text-sm text-muted">{{ loading ? '…' : formatNumber(status?.[i.key] ?? 0) }}</span>
            </RouterLink>
          </li>
        </ul>
      </section>

      <section class="card space-y-5 p-5 sm:p-6">
        <h2 class="font-semibold">En trois étapes</h2>
        <ol class="space-y-4 text-sm">
          <li class="flex flex-wrap items-center gap-3">
            <span class="flex size-7 items-center justify-center rounded-full bg-brand-50 text-xs font-semibold text-brand-700">1</span>
            <span class="min-w-0 flex-1">Téléchargez le classeur modèle (il contient des instructions et un exemple par feuille).</span>
            <button type="button" class="btn btn-secondary" @click="openFile(importsApi.templatePath('setup'))"><AppIcon name="download" class="size-4" />Télécharger le modèle</button>
          </li>
          <li class="flex gap-3">
            <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-brand-50 text-xs font-semibold text-brand-700">2</span>
            <div class="min-w-0 flex-1 space-y-2">
              <p>Remplissez les feuilles utiles (les autres restent vides). Ce qui est cité mais n’existe pas encore est créé automatiquement :</p>
              <ul class="grid gap-1.5 text-[13px] text-muted sm:grid-cols-2">
                <li v-for="s in SHEETS" :key="s[0]"><b class="text-body">{{ s[0] }}</b> — {{ s[1] }}</li>
              </ul>
            </div>
          </li>
          <li class="flex flex-wrap items-center gap-3">
            <span class="flex size-7 items-center justify-center rounded-full bg-brand-50 text-xs font-semibold text-brand-700">3</span>
            <span class="min-w-0 flex-1">Envoyez le fichier : « Vérifier » contrôle tout sans rien enregistrer, puis « Importer ».</span>
            <button type="button" class="btn btn-primary" @click="importOpen = true"><AppIcon name="upload" class="size-4" />Importer mon fichier</button>
          </li>
        </ol>
        <p class="rounded-xl bg-ground p-3 text-[13px] text-muted">
          Un enseignant déjà connu n’est jamais recréé : il est retrouvé (e-mail, téléphone ou nom) et son nouvel établissement est simplement ajouté aux précédents.
          Vous préférez saisir à la main ? Chaque liste (classes, élèves…) garde son bouton « Nouveau ».
        </p>
      </section>

      <section v-if="last" class="card p-5 sm:p-6">
        <h2 class="mb-3 font-semibold">Résultat du dernier import</h2>
        <dl class="grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
          <div><dt class="text-muted">Établissements créés</dt><dd class="font-semibold">{{ last.campuses_created }}</dd></div>
          <div><dt class="text-muted">Enseignants créés</dt><dd class="font-semibold">{{ last.teachers_created }}</dd></div>
          <div><dt class="text-muted">Enseignants rattachés</dt><dd class="font-semibold">{{ last.teachers_linked }}</dd></div>
          <div><dt class="text-muted">Matières créées</dt><dd class="font-semibold">{{ last.subjects_created }}</dd></div>
          <div><dt class="text-muted">Classes créées</dt><dd class="font-semibold">{{ last.classes_created }}</dd></div>
          <div><dt class="text-muted">Affectations</dt><dd class="font-semibold">{{ last.assignments }}</dd></div>
          <div><dt class="text-muted">Élèves inscrits</dt><dd class="font-semibold">{{ last.students_created }}</dd></div>
          <div><dt class="text-muted">Parents créés</dt><dd class="font-semibold">{{ last.parents_created }}</dd></div>
          <div><dt class="text-muted">Parents liés</dt><dd class="font-semibold">{{ last.parents_linked }}</dd></div>
        </dl>
      </section>
    </template>

    <ImportDialog
      :open="importOpen"
      title="Importer le classeur de mise en route"
      endpoint="imports/setup"
      :template-path="importsApi.templatePath('setup')"
      help="Envoyez le classeur Excel (.xlsx) du modèle, avec ses feuilles. En cas d’erreur, la feuille et la ligne concernées sont indiquées et rien n’est enregistré."
      @imported="imported"
      @close="importOpen = false"
    />
  </div>
</template>
