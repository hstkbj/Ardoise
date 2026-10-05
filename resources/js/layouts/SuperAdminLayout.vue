<script setup>
import AppShell from '@/components/layout/AppShell.vue';
import { SUPERADMIN_NAV } from '@/config/navigation';
</script>

<template>
  <AppShell :nav="SUPERADMIN_NAV" space="Console plateforme" dark>
    <template #topbar><span class="text-[13px] font-medium text-muted">Ardoise · administration de la plateforme</span></template>
    <RouterView :key="$route.path" />
  </AppShell>
</template>
