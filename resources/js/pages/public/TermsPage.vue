<script setup>
import LegalDocument from '@/components/layout/LegalDocument.vue';
import { termsSections } from '@/config/legal';

const sections = termsSections();
</script>

<template>
  <LegalDocument title="Conditions générales d’utilisation" intro="Les règles qui s’appliquent à l’utilisation d’Ardoise par un établissement et ses utilisateurs." :sections="sections" />
</template>
