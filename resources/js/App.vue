<script setup>
import ToastContainer from '@/components/ui/ToastContainer.vue';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
</script>

<template>
  <RouterView />
  <ToastContainer />
  <ConfirmDialog />
</template>
