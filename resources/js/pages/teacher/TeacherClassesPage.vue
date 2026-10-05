<script setup>
import PageHeader from '@/components/ui/PageHeader.vue';
import LoadingState from '@/components/ui/LoadingState.vue';
import ErrorState from '@/components/ui/ErrorState.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import ClassCard from '@/components/domain/ClassCard.vue';
import { dashboardApi } from '@/services/api';
import { useAsync } from '@/composables/useAsync';

const { data, loading, error, run } = useAsync(() => dashboardApi.teacherClasses(), { immediate: true, initial: [] });
</script>

<template>
  <div>
    <PageHeader title="Mes classes" subtitle="Classes dont vous êtes professeur principal ou dans lesquelles vous enseignez." />
    <LoadingState v-if="loading" variant="cards" :rows="3" />
    <div v-else-if="error" class="card"><ErrorState :message="error.message" @retry="run" /></div>
    <div v-else-if="!data.length" class="card"><EmptyState title="Aucune classe attribuée" description="La direction vous affecte aux classes depuis la fiche de chaque classe." icon="layers" /></div>
    <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <ClassCard v-for="c in data" :key="c.id" :classroom="c" :to="{ path: '/teacher/students', query: { class_id: c.id } }" />
    </div>
  </div>
</template>
