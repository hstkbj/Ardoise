<script setup>
import { reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { authApi } from '@/services/api';
import { useUiStore } from '@/stores/ui';

const route = useRoute();
const router = useRouter();
const ui = useUiStore();
const form = reactive({
  login: String(route.query.login || ''),
  token: String(route.query.token || ''),
  school_code: String(route.query.school_code || ''),
  password: '',
  password_confirmation: '',
});
const fromLink = !!route.query.token;
const sending = ref(false);
const error = ref('');

async function submit() {
  error.value = '';
  if (form.password.length < 8) return (error.value = 'Le mot de passe doit contenir au moins 8 caractères.');
  if (form.password !== form.password_confirmation) return (error.value = 'Les deux mots de passe ne correspondent pas.');
  sending.value = true;
  try {
    await authApi.resetPassword({ ...form, school_code: form.school_code || undefined });
    ui.toast('Mot de passe modifié. Vous pouvez vous connecter.');
    router.replace('/login');
  } catch (e) {
    error.value = e.message;
  } finally {
    sending.value = false;
  }
}
</script>

<template>
  <div>
    <h1 class="text-2xl font-semibold tracking-tight">Nouveau mot de passe</h1>
    <p class="mt-1 text-sm text-muted">Choisissez un mot de passe d’au moins 8 caractères.</p>
    <form class="mt-6 space-y-4" novalidate @submit.prevent="submit">
      <div class="flex flex-col gap-1"><label for="rp-login" class="label">E-mail ou téléphone</label><input id="rp-login" v-model="form.login" class="input" :readonly="fromLink" autocomplete="username" /></div>
      <div v-if="!fromLink" class="flex flex-col gap-1"><label for="rp-token" class="label">Code reçu par SMS</label><input id="rp-token" v-model="form.token" class="input font-mono tracking-widest" inputmode="numeric" autocomplete="one-time-code" maxlength="6" /></div>
      <div class="flex flex-col gap-1"><label for="rp-pass" class="label">Nouveau mot de passe</label><input id="rp-pass" v-model="form.password" type="password" class="input" autocomplete="new-password" /></div>
      <div class="flex flex-col gap-1"><label for="rp-pass2" class="label">Confirmation</label><input id="rp-pass2" v-model="form.password_confirmation" type="password" class="input" autocomplete="new-password" /></div>
      <p v-if="error" class="rounded-lg bg-danger-50 px-3 py-2.5 text-sm text-danger-600" role="alert">{{ error }}</p>
      <button type="submit" class="btn btn-lg btn-primary w-full" :disabled="sending">{{ sending ? 'Enregistrement…' : 'Enregistrer' }}</button>
    </form>
  </div>
</template>
