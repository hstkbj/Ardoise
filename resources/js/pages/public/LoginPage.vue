<script setup>
/**
 * Connexion.
 *  - Adresse d'une école (palmiers.ardoise.app) : personnel et enseignants de cette école
 *  - Domaine central : console superadmin uniquement
 * Les parents utilisent l'application mobile.
 */
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AppIcon from '@/components/ui/AppIcon.vue';
import { publicApi } from '@/services/api';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
const route = useRoute();
const router = useRouter();

const context = ref(null);
const contextError = ref('');
const profile = computed(() => (context.value?.central ? 'superadmin' : 'school'));
const unknownHost = computed(() => context.value && !context.value.central && !context.value.school);
const form = reactive({ login: '', password: '', remember: true });
const showPassword = ref(false);
const error = ref('');
// Double authentification : 2e étape après le mot de passe
const challenge = ref('');
const otp = reactive({ code: '', recovery: '', useRecovery: false });

onMounted(async () => {
  try {
    context.value = await publicApi.context();
  } catch (e) {
    contextError.value = e.message;
  }
});

function goHome() {
  const redirect = typeof route.query.redirect === 'string' && route.query.redirect.startsWith('/') ? route.query.redirect : null;
  router.replace(auth.securityAction ? '/security' : redirect || auth.homePath);
}

async function submitCode() {
  error.value = '';
  const code = otp.code.replace(/\s/g, '');
  if (otp.useRecovery ? !otp.recovery.trim() : !/^\d{6}$/.test(code)) {
    error.value = otp.useRecovery ? 'Saisissez un de vos codes de secours.' : 'Saisissez les 6 chiffres affichés par votre application.';
    return;
  }
  try {
    await auth.completeTwoFactor({ challenge: challenge.value, code, recoveryCode: otp.useRecovery ? otp.recovery.trim() : undefined, role: profile.value });
    goHome();
  } catch (e) {
    error.value = e.status === 429 ? 'Trop de tentatives. Patientez une minute avant de réessayer.' : e.message;
    if (/expirée/.test(e.message)) cancelTwoFactor(false);
  }
}

function cancelTwoFactor(clearError = true) {
  challenge.value = '';
  Object.assign(otp, { code: '', recovery: '', useRecovery: false });
  form.password = '';
  if (clearError) error.value = '';
}

async function submit() {
  if (challenge.value) return submitCode();
  error.value = '';
  if (!form.login || !form.password) {
    error.value = 'Renseignez votre identifiant et votre mot de passe.';
    return;
  }
  try {
    const out = await auth.login({ login: form.login.trim(), password: form.password, remember: form.remember, role: profile.value });
    if (out?.two_factor) {
      challenge.value = out.challenge;
      return;
    }
    goHome();
  } catch (e) {
    error.value = e.status === 429 ? 'Trop de tentatives. Patientez une minute avant de réessayer.' : e.message;
  }
}
</script>

<template>
  <div>
    <h1 class="text-2xl font-semibold tracking-tight">{{ context?.school ? context.school.name : context?.central ? 'Console plateforme' : 'Connexion' }}</h1>
    <p class="mt-1 text-sm text-muted">{{ context?.central ? 'Accès réservé à l’équipe de la plateforme. Les établissements se connectent depuis leur propre adresse.' : 'Connectez-vous avec votre e-mail ou votre téléphone.' }}</p>

    <p v-if="contextError" class="mt-6 rounded-lg bg-danger-50 px-3 py-2.5 text-sm text-danger-600" role="alert">{{ contextError }}</p>
    <p v-else-if="unknownHost" class="mt-6 rounded-xl bg-warn-50 p-4 text-sm text-warn-700" role="alert">Cette adresse ne correspond à aucun établissement. Vérifiez le lien fourni par votre établissement.</p>

    <form v-if="challenge" class="mt-6 space-y-4" novalidate @submit.prevent="submit">
      <div class="flex gap-3 rounded-xl bg-mint p-4 text-[13px] text-body">
        <AppIcon name="shield" class="mt-0.5 size-4 shrink-0" />
        <span v-if="!otp.useRecovery">Ouvrez votre application d’authentification et saisissez le code à 6 chiffres affiché pour <b>{{ form.login }}</b>.</span>
        <span v-else>Saisissez un de vos codes de secours. Chaque code ne fonctionne qu’une fois.</span>
      </div>
      <div v-if="!otp.useRecovery" class="flex flex-col gap-1">
        <label for="otp" class="label">Code de vérification</label>
        <input id="otp" v-model="otp.code" class="input h-14 text-center font-mono text-2xl tracking-[0.4em]" inputmode="numeric" autocomplete="one-time-code" maxlength="7" autofocus :aria-invalid="!!error" />
      </div>
      <div v-else class="flex flex-col gap-1">
        <label for="recovery" class="label">Code de secours</label>
        <input id="recovery" v-model="otp.recovery" class="input h-12 text-center font-mono text-lg uppercase" placeholder="XXXXX-XXXXX" autocapitalize="characters" spellcheck="false" :aria-invalid="!!error" />
      </div>
      <p v-if="error" class="rounded-lg bg-danger-50 px-3 py-2.5 text-sm text-danger-600" role="alert">{{ error }}</p>
      <button type="submit" class="btn btn-lg btn-primary w-full" :disabled="auth.loading">{{ auth.loading ? 'Vérification…' : 'Valider' }}</button>
      <div class="flex justify-between text-[13px]">
        <button type="button" class="link" @click="otp.useRecovery = !otp.useRecovery; error = ''">{{ otp.useRecovery ? 'Utiliser l’application' : 'Utiliser un code de secours' }}</button>
        <button type="button" class="link" @click="cancelTwoFactor()">Retour</button>
      </div>
    </form>

    <template v-else-if="context && !unknownHost">
    <p v-if="route.query.expired" class="mt-6 rounded-xl bg-warn-50 p-4 text-sm text-warn-700" role="status">
      L’abonnement de votre établissement a expiré. Seul l’administrateur peut se connecter pour le renouveler.
    </p>

    <form class="mt-6 space-y-4" novalidate @submit.prevent="submit">
        <div class="flex flex-col gap-1">
          <label for="login" class="label">{{ profile === 'superadmin' ? 'E-mail' : 'E-mail ou téléphone' }}</label>
          <input id="login" v-model="form.login" class="input" :type="profile === 'superadmin' ? 'email' : 'text'" autocomplete="username" :aria-invalid="!!error" />
        </div>
        <div class="flex flex-col gap-1">
          <div class="flex items-center justify-between">
            <label for="password" class="label">Mot de passe</label>
            <RouterLink v-if="profile === 'school'" to="/forgot-password" class="text-[13px] link">Mot de passe oublié ?</RouterLink>
          </div>
          <div class="relative">
            <input id="password" v-model="form.password" :type="showPassword ? 'text' : 'password'" class="input pr-11" autocomplete="current-password" :aria-invalid="!!error" />
            <button type="button" class="absolute top-1/2 right-1 flex size-8 -translate-y-1/2 items-center justify-center text-subtle hover:text-ink" :aria-label="showPassword ? 'Masquer le mot de passe' : 'Afficher le mot de passe'" @click="showPassword = !showPassword"><AppIcon name="eye" class="size-4" /></button>
          </div>
        </div>
        <label class="flex items-center gap-2 text-sm text-body"><input v-model="form.remember" type="checkbox" class="size-4 accent-brand-600" />Rester connecté</label>

      <p v-if="error" class="rounded-lg bg-danger-50 px-3 py-2.5 text-sm text-danger-600" role="alert">{{ error }}</p>

      <button type="submit" class="btn btn-lg btn-primary w-full" :disabled="auth.loading">{{ auth.loading ? 'Connexion…' : 'Se connecter' }}</button>
    </form>

    </template>
    <p class="mt-4 text-center text-[13px]"><RouterLink :to="{ path: '/guide', query: { pour: 'ecole' } }" class="link">Besoin d’aide ? Consultez le guide d’utilisation</RouterLink></p>
  </div>
</template>
