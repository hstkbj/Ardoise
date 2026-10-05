<script setup>
/** Fiche enseignant : informations + emploi du temps. */
import { ref } from 'vue';
import { useRoute } from 'vue-router';
import ResourceShow from '@/pages/generic/ResourceShow.vue';
import TimetableGrid from '@/components/domain/TimetableGrid.vue';
import { timetableApi } from '@/services/api';

const route = useRoute();
const entries = ref([]);
timetableApi.get({ teacher_id: route.params.id }).then((d) => (entries.value = d)).catch(() => {});
</script>

<template>
  <ResourceShow resource="teachers" base="/admin/teachers">
    <section class="card mb-5 p-5">
      <h2 class="card-title">Emploi du temps</h2>
      <div class="mt-4 overflow-x-auto"><TimetableGrid :entries="entries" show-class /></div>
    </section>
  </ResourceShow>
</template>
