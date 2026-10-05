<script setup>
/** Gabarit des pages de l'espace parent : titre, choix de l'enfant, états, contenu. */
import LoadingState from '@/components/ui/LoadingState.vue';
import ErrorState from '@/components/ui/ErrorState.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import ChildSwitcher from './ChildSwitcher.vue';
import { useParentChildren } from '@/composables/useParentChildren';

defineProps({ title: { type: String, required: true }, wide: { type: Boolean, default: false } });
const { children, child, loading, error, reload, select } = useParentChildren();
</script>

<template>
  <div class="mx-auto space-y-4" :class="wide ? 'max-w-4xl' : 'max-w-2xl'">
    <div class="flex flex-wrap items-end justify-between gap-3">
      <h1 class="text-2xl font-semibold tracking-tight">{{ title }}</h1>
      <slot name="actions" :child="child" />
    </div>
    <LoadingState v-if="loading && !children.length" variant="cards" :rows="2" />
    <div v-else-if="error" class="card"><ErrorState :message="error.message" @retry="reload" /></div>
    <div v-else-if="!child" class="card"><EmptyState title="Aucun enfant rattaché à votre compte" description="Contactez le secrétariat de l’établissement." icon="users" /></div>
    <template v-else>
      <ChildSwitcher :items="children" :selected-id="child.id" @select="select" />
      <slot :child="child" :reload="reload" />
    </template>
  </div>
</template>
