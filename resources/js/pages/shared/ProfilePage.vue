<script setup>
import { reactive, ref } from 'vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import Avatar from '@/components/ui/Avatar.vue';
import { useAuthStore, ROLE_LABELS } from '@/stores/auth';
import { useUiStore } from '@/stores/ui';
import { profileApi } from '@/services/api';
import { useFormErrors } from '@/composables/useFormErrors';

const auth = useAuthStore();
const ui = useUiStore();
const isParent = auth.hasRole('parent');

const info = reactive({ name: auth.user?.name ?? '', email: auth.user?.email ?? '', phone: auth.user?.phone ?? '' });
const pass = reactive({ current: '', password: '', password_confirmation: '' });
const infoErrors = useFormErrors();
const passErrors = useFormErrors();
const savingInfo = ref(false);
const savingPass = ref(false);

async function saveInfo() {
  if (!infoErrors.validateRequired([{ key: 'name', required: true }, { key: 'email', type: 'email' }], info)) return;
  savingInfo.value = true;
  try {
    auth.setUser(await profileApi.update(info));
    ui.toast('Profil mis à jour.');
  } catch (e) {
    infoErrors.setServerErrors(e.errors);
    ui.error(e);
  } finally {
    savingInfo.value = false;
  }
}

async function savePassword() {
  passErrors.errors.value = {};
  if (pass.password.length < 8) return (passErrors.errors.value = { password: 'Au moins 8 caractères.' });
  if (pass.password !== pass.password_confirmation) return (passErrors.errors.value = { password_confirmation: 'Les mots de passe ne correspondent pas.' });
  savingPass.value = true;
  try {
    await profileApi.password(pass);
    Object.assign(pass, { current: '', password: '', password_confirmation: '' });
    ui.toast('Mot de passe modifié.');
  } catch (e) {
    passErrors.setServerErrors(e.errors);
    ui.error(e);
  } finally {
    savingPass.value = false;
  }
}
</script>

<template>
  <div class="mx-auto max-w-3xl space-y-6">
    <PageHeader title="Mon profil" subtitle="Vos coordonnées et votre sécurité." />

    <section class="card flex items-center gap-4 p-5">
      <Avatar :name="auth.user?.name || ''" size="lg" />
      <div>
        <p class="font-semibold">{{ auth.user?.name }}</p>
        <p class="text-sm text-muted">{{ ROLE_LABELS[auth.role] }}<template v-if="auth.user?.tenant"> · {{ auth.user.tenant.name }}</template></p>
      </div>
    </section>

    <form class="card space-y-4 p-5" novalidate @submit.prevent="saveInfo">
      <h2 class="card-title">Coordonnées</h2>
      <div class="grid gap-4 sm:grid-cols-2">
        <div class="flex flex-col gap-1 sm:col-span-2"><label for="p-name" class="label">Nom complet</label><input id="p-name" v-model="info.name" class="input" :aria-invalid="!!infoErrors.errors.value.name" /><p v-if="infoErrors.errors.value.name" class="text-xs text-danger-600">{{ infoErrors.errors.value.name }}</p></div>
        <div class="flex flex-col gap-1"><label for="p-email" class="label">E-mail</label><input id="p-email" v-model="info.email" type="email" class="input" /><p v-if="infoErrors.errors.value.email" class="text-xs text-danger-600">{{ infoErrors.errors.value.email }}</p></div>
        <div class="flex flex-col gap-1"><label for="p-phone" class="label">Téléphone</label><input id="p-phone" v-model="info.phone" type="tel" class="input" /></div>
      </div>
      <div class="flex justify-end"><button type="submit" class="btn btn-primary" :disabled="savingInfo">Enregistrer</button></div>
    </form>

    <section v-if="isParent" class="card space-y-2 p-5">
      <h2 class="card-title">Connexion</h2>
      <p class="text-sm text-muted">Vous vous connectez avec le code d’accès remis par l’établissement. En cas de perte, demandez-en un nouveau au secrétariat : l’ancien code sera immédiatement désactivé.</p>
    </section>

    <form v-else-if="!auth.isPlatform" class="card space-y-4 p-5" novalidate @submit.prevent="savePassword">
      <h2 class="card-title">Mot de passe</h2>
      <div class="grid gap-4 sm:grid-cols-3">
        <div class="flex flex-col gap-1"><label for="p-cur" class="label">Actuel</label><input id="p-cur" v-model="pass.current" type="password" class="input" autocomplete="current-password" /><p v-if="passErrors.errors.value.current" class="text-xs text-danger-600">{{ passErrors.errors.value.current }}</p></div>
        <div class="flex flex-col gap-1"><label for="p-new" class="label">Nouveau</label><input id="p-new" v-model="pass.password" type="password" class="input" autocomplete="new-password" /><p v-if="passErrors.errors.value.password" class="text-xs text-danger-600">{{ passErrors.errors.value.password }}</p></div>
        <div class="flex flex-col gap-1"><label for="p-conf" class="label">Confirmation</label><input id="p-conf" v-model="pass.password_confirmation" type="password" class="input" autocomplete="new-password" /><p v-if="passErrors.errors.value.password_confirmation" class="text-xs text-danger-600">{{ passErrors.errors.value.password_confirmation }}</p></div>
      </div>
      <div class="flex justify-end"><button type="submit" class="btn btn-primary" :disabled="savingPass">Modifier le mot de passe</button></div>
    </form>
  </div>
</template>
