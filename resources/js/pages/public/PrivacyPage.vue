<script setup>
import LegalDocument from '@/components/layout/LegalDocument.vue';
import { privacySections } from '@/config/legal';

const sections = privacySections();
</script>

<template>
  <LegalDocument title="Politique de confidentialité" intro="Vos données, celles de vos enfants et de votre école : ce que nous en faisons, et ce que nous n’en faisons pas." :sections="sections" />
</template>
