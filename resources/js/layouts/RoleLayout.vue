<script setup>
/** Pages communes (profil) : affichées dans la coque de l'espace de l'utilisateur. */
import { computed } from 'vue';
import SchoolAdminLayout from './SchoolAdminLayout.vue';
import TeacherLayout from './TeacherLayout.vue';
import ParentLayout from './ParentLayout.vue';
import SuperAdminLayout from './SuperAdminLayout.vue';
import { useAuthStore, STAFF_ROLES } from '@/stores/auth';

const auth = useAuthStore();
const layout = computed(() => {
  if (auth.isPlatform) return SuperAdminLayout;
  if (auth.hasRole(...STAFF_ROLES)) return SchoolAdminLayout;
  if (auth.hasRole('teacher')) return TeacherLayout;
  return ParentLayout;
});
</script>

<template>
  <component :is="layout" />
</template>
