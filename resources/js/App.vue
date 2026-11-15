<script setup>
import ToastContainer from '@/components/ui/ToastContainer.vue';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import CookieBanner from '@/components/layout/CookieBanner.vue';
</script>

<template>
  <RouterView />
  <ToastContainer />
  <ConfirmDialog />
  <CookieBanner />
</template>
