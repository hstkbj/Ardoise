<script setup>
/** Grille des offres. Les plans actifs viennent de l'API ; à défaut, la grille standard est affichée. */
import { computed, onMounted, ref } from 'vue';
import AppIcon from '@/components/ui/AppIcon.vue';
import { publicApi } from '@/services/api';
import { formatMoney } from '@/utils/format';

const FALLBACK = [
  { name: 'Essentiel', description: 'Pour une école qui démarre.', price: null, period: 'yearly', features: ['Notes et bulletins', 'Absences', 'Espace parent'] },
  { name: 'Établissement', description: 'La gestion complète d’un établissement.', price: null, period: 'yearly', features: ['Tout Essentiel', 'Frais et paiements', 'Emplois du temps', 'Notifications SMS'] },
  { name: 'Groupe scolaire', description: 'Plusieurs sites, une seule vue.', price: null, period: 'yearly', features: ['Tout Établissement', 'Tableaux consolidés', 'Support prioritaire'] },
];
const TINTS = ['bg-mint', 'bg-sun-soft', 'bg-lavender'];

const plans = ref(FALLBACK);
onMounted(async () => {
  try {
    const data = await publicApi.plans();
    if (data?.length) plans.value = data;
  } catch {
    /* grille par défaut */
  }
});
const featuredIndex = computed(() => (plans.value.length >= 3 ? 1 : 0));
</script>

<template>
  <div class="grid gap-5 md:grid-cols-3">
    <article v-for="(p, i) in plans" :key="p.name" class="relative flex flex-col rounded-[28px] p-7" :class="[TINTS[i % 3], i === featuredIndex ? 'ring-2 ring-brand-600' : '']">
      <span v-if="i === featuredIndex" class="absolute -top-3 left-7 rounded-full bg-brand-600 px-3 py-1 text-xs font-semibold text-white">Le plus choisi</span>
      <h3 class="text-xl font-semibold">{{ p.name }}</h3>
      <p class="mt-1 min-h-10 text-sm text-muted">{{ p.description }}</p>
      <p class="mt-5 text-3xl font-semibold tracking-tight">
        <template v-if="p.price">{{ formatMoney(p.price) }}<span class="text-sm font-medium text-muted"> / {{ p.period === 'monthly' ? 'mois' : 'an' }}</span></template>
        <template v-else>Sur devis</template>
      </p>
      <ul class="mt-6 flex-1 space-y-2.5 text-sm">
        <li v-for="f in p.features" :key="f" class="flex items-start gap-2"><AppIcon name="check" class="mt-0.5 size-4 shrink-0 text-brand-600" />{{ f }}</li>
        <li v-if="p.max_students" class="flex items-start gap-2 text-muted"><AppIcon name="users" class="mt-0.5 size-4 shrink-0" />Jusqu’à {{ p.max_students.toLocaleString('fr-FR') }} élèves</li>
      </ul>
      <RouterLink to="/contact" class="btn mt-7 rounded-full" :class="i === featuredIndex ? 'btn-primary' : 'btn-secondary'">Demander une démo</RouterLink>
    </article>
  </div>
</template>
