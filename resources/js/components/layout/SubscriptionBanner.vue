<script setup>
/**
 * Bandeau d'abonnement (personnel de l'école) :
 *  - essai / abonnement qui se termine dans 7 jours ou moins ;
 *  - délai de grâce : l'accès sera bloqué à sa date de fin.
 */
import { computed } from 'vue';
import AppIcon from '@/components/ui/AppIcon.vue';
import { useAuthStore } from '@/stores/auth';
import { formatDate } from '@/utils/format';

const auth = useAuthStore();
const sub = computed(() => auth.subscription);
const canPay = computed(() => auth.hasRole('school_admin', 'director'));

const banner = computed(() => {
  const s = sub.value;
  if (!s) return null;
  if (s.state === 'grace') {
    return { tone: 'danger', text: `L’abonnement a expiré le ${formatDate(s.ends_at)}. L’accès sera bloqué pour toute l’école le ${formatDate(s.grace_ends_at)} sans renouvellement.` };
  }
  if (['trial', 'active'].includes(s.state) && s.days_left !== null && s.days_left <= 7) {
    const what = s.is_trial ? 'La période d’essai' : 'L’abonnement';
    const when = s.days_left <= 0 ? 'aujourd’hui' : `dans ${s.days_left} jour${s.days_left > 1 ? 's' : ''}`;
    return { tone: 'warning', text: `${what} se termine ${when} (${formatDate(s.ends_at)}).` };
  }
  return null;
});
</script>

<template>
  <div v-if="banner" class="mb-5 flex flex-wrap items-center gap-3 rounded-xl px-4 py-3 text-sm print:hidden" :class="banner.tone === 'danger' ? 'bg-danger-50 text-danger-600' : 'bg-warn-50 text-warn-700'" role="status">
    <AppIcon name="alert" class="size-5 shrink-0" />
    <p class="min-w-0 flex-1">{{ banner.text }}<template v-if="!canPay"> Prévenez la direction.</template></p>
    <RouterLink v-if="canPay" to="/admin/billing" class="btn btn-sm" :class="banner.tone === 'danger' ? 'btn-danger' : 'btn-secondary'">Renouveler</RouterLink>
  </div>
</template>
