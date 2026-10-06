<script setup>
import AppShell from '@/components/layout/AppShell.vue';
import ContextSwitcher from '@/components/layout/ContextSwitcher.vue';
import SubscriptionBanner from '@/components/layout/SubscriptionBanner.vue';
import { ADMIN_NAV } from '@/config/navigation';
</script>

<template>
  <AppShell :nav="ADMIN_NAV" space="Administration" notifications-to="/admin/notifications">
    <template #topbar><ContextSwitcher /></template>
    <SubscriptionBanner />
    <RouterView :key="$route.path" />
  </AppShell>
</template>
