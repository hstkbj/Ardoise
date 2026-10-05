<script setup>
import { useRouter } from 'vue-router';
import LoadingState from '@/components/ui/LoadingState.vue';
import ErrorState from '@/components/ui/ErrorState.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import Avatar from '@/components/ui/Avatar.vue';
import AppIcon from '@/components/ui/AppIcon.vue';
import { useParentChildren } from '@/composables/useParentChildren';
import { formatScore } from '@/utils/format';

const router = useRouter();
const { children, loading, error, reload, select } = useParentChildren();
function open(c) {
  select(c.id);
  router.push(`/parent/children/${c.id}`);
}
</script>

<template>
  <div class="mx-auto max-w-2xl space-y-4">
    <h1 class="text-2xl font-semibold tracking-tight">Mes enfants</h1>
    <LoadingState v-if="loading && !children.length" variant="cards" :rows="2" />
    <div v-else-if="error" class="card"><ErrorState :message="error.message" @retry="reload" /></div>
    <div v-else-if="!children.length" class="card"><EmptyState title="Aucun enfant rattaché" description="Contactez le secrétariat de l’établissement." icon="users" /></div>
    <template v-else>
    <button v-for="c in children" :key="c.id" type="button" class="card flex w-full items-center gap-4 p-4 text-left hover:border-brand-300" @click="open(c)">
      <Avatar :name="c.full_name" size="lg" />
      <span class="min-w-0 flex-1">
        <span class="block truncate font-semibold">{{ c.full_name }}</span>
        <span class="block truncate text-sm text-muted">{{ c.class_name }} · {{ c.school_name }}</span>
        <span class="text-xs text-subtle">Professeur principal : {{ c.head_teacher || '—' }}</span>
      </span>
      <span class="text-right"><span class="block text-lg font-semibold tabular">{{ formatScore(c.general_average) }}</span><span class="text-xs text-subtle">/20</span></span>
      <AppIcon name="chevron-right" class="size-5 text-subtle" />
    </button>
    </template>
  </div>
</template>
