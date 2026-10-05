<script setup>
/** Fiche d'un enfant : sélectionne l'enfant puis présente ses moyennes par matière. */
import { watchEffect } from 'vue';
import { useRoute } from 'vue-router';
import ParentSection from '@/components/domain/ParentSection.vue';
import HBarList from '@/components/ui/HBarList.vue';
import { useParentChildren } from '@/composables/useParentChildren';
import { formatScore } from '@/utils/format';

const route = useRoute();
const { select } = useParentChildren();
watchEffect(() => select(Number(route.params.id)));
</script>

<template>
  <ParentSection title="Fiche de l’enfant">
    <template #default="{ child }">
      <section class="card p-5">
        <h2 class="text-lg font-semibold">{{ child.full_name }}</h2>
        <dl class="mt-3 grid grid-cols-2 gap-3 text-sm">
          <div><dt class="text-xs text-subtle">Classe</dt><dd>{{ child.class_name }}</dd></div>
          <div><dt class="text-xs text-subtle">Matricule</dt><dd>{{ child.matricule }}</dd></div>
          <div><dt class="text-xs text-subtle">Établissement</dt><dd>{{ child.school_name }}</dd></div>
          <div><dt class="text-xs text-subtle">Professeur principal</dt><dd>{{ child.head_teacher || '—' }}</dd></div>
        </dl>
      </section>
      <section class="card p-5">
        <div class="flex items-baseline justify-between"><h2 class="card-title">Moyennes par matière</h2><span class="text-sm">Générale : <b class="tabular">{{ formatScore(child.general_average) }}</b>/20</span></div>
        <div class="mt-4">
          <HBarList v-if="child.subjects.some((s) => s.average != null)" :items="child.subjects.filter((s) => s.average != null).map((s) => ({ label: s.name, value: s.average }))" :show-share="false" :format="(v) => formatScore(v)" />
          <p v-else class="text-sm text-subtle">Pas encore de moyenne sur la période.</p>
        </div>
      </section>
      <div class="grid grid-cols-2 gap-3">
        <RouterLink to="/parent/report-cards" class="btn btn-secondary">Bulletins</RouterLink>
        <RouterLink to="/parent/timetable" class="btn btn-secondary">Emploi du temps</RouterLink>
      </div>
    </template>
  </ParentSection>
</template>
