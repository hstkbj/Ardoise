<script setup>
/**
 * Connexion.
 *  - Établissement (personnel, enseignants) : identifiant + mot de passe (+ code établissement hors sous-domaine)
 *  - Parent : code d'accès uniquement — le serveur retrouve l'école et le parent
 *  - Plateforme : console superadmin
 */
import { computed, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AppIcon from '@/components/ui/AppIcon.vue';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
const route = useRoute();
const router = useRouter();

const profiles = [
  { key: 'school', label: 'Établissement', icon: 'building' },
  { key: 'parent', label: 'Parent', icon: 'home' },
  { key: 'superadmin', label: 'Plateforme', icon: 'shield' },
];
const profile = ref(['parent', 'superadmin'].includes(route.query.profile) ? route.query.profile : 'school');
const form = reactive({ login: '', password: '', schoolCode: '', remember: true, code: '' });
const showPassword = ref(false);
const error = ref('');

// Sur un sous-domaine d'école, le code établissement est inutile
const onSchoolHost = computed(() => {
  const host = window.location.hostname;
  const parts = host.split('.');
  return !/^\d+\.\d+\.\d+\.\d+$/.test(host) && parts.length > (host.endsWith('localhost') ? 1 : 2);
});

function formatCode(e) {
  const raw = e.target.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 12);
  form.code = raw.match(/.{1,4}/g)?.join('-') ?? '';
}

async function submit() {
  error.value = '';
  try {
    if (profile.value === 'parent') {
      if (form.code.replace(/-/g, '').length !== 12) {
        error.value = 'Le code d’accès comporte 12 caractères (ex. ABCD-EF23-GH45).';
        return;
      }
      await auth.loginWithCode(form.code);
    } else {
      if (!form.login || !form.password) {
        error.value = 'Renseignez votre identifiant et votre mot de passe.';
        return;
      }
      await auth.login({ login: form.login.trim(), password: form.password, schoolCode: form.schoolCode.trim(), remember: form.remember, role: profile.value });
    }
    const redirect = typeof route.query.redirect === 'string' && route.query.redirect.startsWith('/') ? route.query.redirect : null;
    router.replace(redirect || auth.homePath);
  } catch (e) {
    error.value = e.status === 429 ? 'Trop de tentatives. Patientez une minute avant de réessayer.' : e.message;
  }
}
</script>

<template>
  <div>
    <h1 class="text-2xl font-semibold tracking-tight">Connexion</h1>
    <p class="mt-1 text-sm text-muted">Choisissez votre profil pour accéder à votre espace.</p>

    <div class="mt-6 grid grid-cols-3 gap-1 rounded-xl bg-white p-1 ring-1 ring-line" role="radiogroup" aria-label="Profil">
      <button v-for="p in profiles" :key="p.key" type="button" role="radio" :aria-checked="profile === p.key" class="flex h-11 items-center justify-center gap-2 rounded-lg text-[13px] font-medium" :class="profile === p.key ? 'bg-brand-600 text-white' : 'text-body hover:bg-ground'" @click="profile = p.key; error = ''">
        <AppIcon :name="p.icon" class="size-4" />{{ p.label }}
      </button>
    </div>

    <p v-if="route.query.expired" class="mt-6 rounded-xl bg-warn-50 p-4 text-sm text-warn-700" role="status">
      L’abonnement de votre établissement a expiré. Seul l’administrateur peut se connecter pour le renouveler.
    </p>

    <form class="mt-6 space-y-4" novalidate @submit.prevent="submit">
      <template v-if="profile === 'parent'">
        <div class="rounded-xl bg-mint p-4 text-[13px] text-body">
          Saisissez le <b>code d’accès</b> remis par l’établissement de votre enfant. Aucun mot de passe n’est nécessaire.
        </div>
        <div class="flex flex-col gap-1">
          <label for="code" class="label">Code d’accès</label>
          <input id="code" :value="form.code" class="input h-14 text-center font-mono text-xl tracking-[0.2em] uppercase" placeholder="XXXX-XXXX-XXXX" autocomplete="one-time-code" autocapitalize="characters" spellcheck="false" inputmode="text" :aria-invalid="!!error" @input="formatCode" />
        </div>
      </template>

      <template v-else>
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
        <div v-if="profile === 'school' && !onSchoolHost" class="flex flex-col gap-1">
          <label for="school-code" class="label">Code établissement</label>
          <input id="school-code" v-model="form.schoolCode" class="input" placeholder="ex. palmiers" autocapitalize="none" spellcheck="false" />
          <p class="text-xs text-subtle">Inutile si vous passez par l’adresse de votre école (ex. palmiers.ardoise.app).</p>
        </div>
        <label class="flex items-center gap-2 text-sm text-body"><input v-model="form.remember" type="checkbox" class="size-4 accent-brand-600" />Rester connecté</label>
      </template>

      <p v-if="error" class="rounded-lg bg-danger-50 px-3 py-2.5 text-sm text-danger-600" role="alert">{{ error }}</p>

      <button type="submit" class="btn btn-lg btn-primary w-full" :disabled="auth.loading">{{ auth.loading ? 'Connexion…' : 'Se connecter' }}</button>
    </form>

    <p v-if="profile === 'parent'" class="mt-6 text-center text-[13px] text-muted">Code perdu ? Demandez-en un nouveau au secrétariat : l’ancien sera désactivé.</p>
  </div>
</template>
