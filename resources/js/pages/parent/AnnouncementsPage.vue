<script setup>
import LoadingState from '@/components/ui/LoadingState.vue';
import ErrorState from '@/components/ui/ErrorState.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import { parentApi } from '@/services/api';
import { useAsync } from '@/composables/useAsync';
import { formatDate } from '@/utils/format';

const { data, loading, error, run } = useAsync(() => parentApi.announcements(), { immediate: true, initial: [] });
</script>

<template>
  <div class="mx-auto max-w-2xl space-y-4">
    <h1 class="text-2xl font-semibold tracking-tight">Annonces</h1>
    <LoadingState v-if="loading" variant="cards" :rows="2" />
    <div v-else-if="error" class="card"><ErrorState :message="error.message" @retry="run" /></div>
    <div v-else-if="!data.length" class="card"><EmptyState title="Aucune annonce" icon="megaphone" /></div>
    <template v-else>
    <article v-for="a in data" :key="a.id" class="card p-5">
      <p class="text-xs text-subtle">{{ formatDate(a.published_at, { day: 'numeric', month: 'long', year: 'numeric' }) }} · {{ a.author }}</p>
      <h2 class="mt-1 font-semibold">{{ a.title }}</h2>
      <p class="mt-2 text-sm whitespace-pre-line text-body">{{ a.body }}</p>
    </article>
    </template>
  </div>
</template>
