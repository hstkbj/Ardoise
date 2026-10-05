<script setup>
import { ref } from 'vue';
import { authApi } from '@/services/api';

const login = ref('');
const schoolCode = ref('');
const sending = ref(false);
const done = ref('');
const error = ref('');
const isPhone = () => /^[+\d][\d\s.-]{6,}$/.test(login.value.trim());

async function submit() {
  error.value = '';
  if (!login.value.trim()) return (error.value = 'Indiquez votre e-mail ou votre téléphone.');
  sending.value = true;
  try {
    const res = await authApi.forgotPassword({ login: login.value.trim(), school_code: schoolCode.value.trim() || undefined });
    done.value = res.message;
  } catch (e) {
    error.value = e.message;
  } finally {
    sending.value = false;
  }
}
</script>

<template>
  <div>
    <h1 class="text-2xl font-semibold tracking-tight">Mot de passe oublié</h1>
    <p class="mt-1 text-sm text-muted">Par e-mail, vous recevez un lien ; par téléphone, un code à 6 chiffres.</p>
    <div v-if="done" class="mt-6 space-y-4" role="status">
      <p class="rounded-xl bg-mint p-4 text-sm">{{ done }}</p>
      <RouterLink v-if="isPhone()" :to="{ path: '/reset-password', query: { login, school_code: schoolCode || undefined } }" class="btn btn-primary w-full">J’ai reçu un code</RouterLink>
      <RouterLink to="/login" class="btn btn-secondary w-full">Retour à la connexion</RouterLink>
    </div>
    <form v-else class="mt-6 space-y-4" novalidate @submit.prevent="submit">
      <div class="flex flex-col gap-1"><label for="fp-login" class="label">E-mail ou téléphone</label><input id="fp-login" v-model="login" class="input" autocomplete="username" /></div>
      <div class="flex flex-col gap-1"><label for="fp-school" class="label">Code établissement <span class="font-normal text-subtle">(si demandé)</span></label><input id="fp-school" v-model="schoolCode" class="input" autocapitalize="none" /></div>
      <p v-if="error" class="rounded-lg bg-danger-50 px-3 py-2.5 text-sm text-danger-600" role="alert">{{ error }}</p>
      <button type="submit" class="btn btn-lg btn-primary w-full" :disabled="sending">{{ sending ? 'Envoi…' : 'Recevoir un lien ou un code' }}</button>
      <RouterLink to="/login" class="block text-center text-sm link">Retour à la connexion</RouterLink>
    </form>
  </div>
</template>
