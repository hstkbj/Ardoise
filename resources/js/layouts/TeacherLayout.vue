<script setup>
import AppShell from '@/components/layout/AppShell.vue';
import ContextSwitcher from '@/components/layout/ContextSwitcher.vue';
import { TEACHER_NAV } from '@/config/navigation';
</script>

<template>
  <AppShell :nav="TEACHER_NAV" space="Espace enseignant" notifications-to="/teacher/notifications">
    <template #topbar><ContextSwitcher /></template>
    <RouterView :key="$route.path" />
  </AppShell>
</template>
