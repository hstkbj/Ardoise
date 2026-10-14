<script setup>
/**
 * Sécurité du compte (personnel, enseignants, superadmin) :
 *  - mot de passe provisoire à remplacer à la première connexion ;
 *  - double authentification par application (Google Authenticator, Microsoft Authenticator, Authy…)
 *    avec codes de secours.
 */
import { computed, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import QRCode from 'qrcode';
import PageHeader from '@/components/ui/PageHeader.vue';
import AppIcon from '@/components/ui/AppIcon.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import { useAuthStore } from '@/stores/auth';
import { useUiStore } from '@/stores/ui';
import { securityApi } from '@/services/api';

const auth = useAuthStore();
const ui = useUiStore();
const router = useRouter();
const api = securityApi(auth.isPlatform);
const minLength = auth.isPlatform ? 10 : 8;

const security = computed(() => auth.security ?? {});
const forcedPassword = computed(() => !!security.value.must_change_password);
const forcedTwoFactor = computed(() => security.value.pending_action === 'two_factor_setup_required');
// Arrivée forcée (mot de passe provisoire, 2FA obligatoire) : retour à l'accueil une fois le compte sécurisé
const forcedFlow = !!auth.securityAction;

// ── Mot de passe ────────────────────────────────────────────────────────────
const pass = reactive({ current: '', password: '', password_confirmation: '' });
const passErrors = ref({});
const savingPass = ref(false);

async function savePassword() {
  passErrors.value = {};
  if (pass.password.length < minLength) return (passErrors.value = { password: `Au moins ${minLength} caractères.` });
  if (pass.password !== pass.password_confirmation) return (passErrors.value = { password_confirmation: 'Les mots de passe ne correspondent pas.' });
  savingPass.value = true;
  try {
    const out = await api.password(pass);
    Object.assign(pass, { current: '', password: '', password_confirmation: '' });
    ui.toast('Mot de passe modifié.');
    if (out?.data) auth.setUser(out.data);
    await continueIfSecured();
  } catch (e) {
    passErrors.value = Object.fromEntries(Object.entries(e.errors || {}).map(([k, v]) => [k, v[0]]));
    if (!Object.keys(passErrors.value).length) ui.error(e);
  } finally {
    savingPass.value = false;
  }
}

// ── Double authentification ────────────────────────────────────────────────
const setup = ref(null); // { secret, otpauth_url, qr }
const code = ref('');
const codeError = ref('');
const recoveryCodes = ref([]);
const busy = ref(false);
const confirmPassword = ref('');
const passwordAction = ref(''); // 'regenerate' | 'disable'
const passwordError = ref('');

function updateSecurity(summary) {
  const { recovery_codes: _codes, ...rest } = summary || {};
  auth.setUser({ security: { ...security.value, ...rest } });
}

async function startSetup() {
  busy.value = true;
  try {
    const out = await api.setup();
    setup.value = { ...out, qr: await QRCode.toDataURL(out.otpauth_url, { width: 220, margin: 1 }) };
    code.value = '';
    codeError.value = '';
  } catch (e) {
    ui.error(e);
  } finally {
    busy.value = false;
  }
}

async function confirmSetup() {
  codeError.value = '';
  if (!/^\d{6}$/.test(code.value.replace(/\s/g, ''))) return (codeError.value = 'Saisissez les 6 chiffres affichés par l’application.');
  busy.value = true;
  try {
    const out = await api.confirm(code.value.replace(/\s/g, ''));
    recoveryCodes.value = out.recovery_codes;
    setup.value = null;
    updateSecurity(out);
    ui.toast('Double authentification activée.');
  } catch (e) {
    codeError.value = e.errors?.code?.[0] || e.message;
  } finally {
    busy.value = false;
  }
}

async function runPasswordAction() {
  passwordError.value = '';
  busy.value = true;
  try {
    if (passwordAction.value === 'regenerate') {
      const out = await api.recoveryCodes(confirmPassword.value);
      recoveryCodes.value = out.recovery_codes;
      updateSecurity(out);
      ui.toast('Nouveaux codes de secours générés : les anciens ne fonctionnent plus.');
    } else {
      updateSecurity(await api.disable(confirmPassword.value));
      recoveryCodes.value = [];
      ui.toast('Double authentification désactivée.');
    }
    passwordAction.value = '';
    confirmPassword.value = '';
  } catch (e) {
    passwordError.value = e.errors?.password?.[0] || e.message;
  } finally {
    busy.value = false;
  }
}

function copyCodes() {
  navigator.clipboard?.writeText(recoveryCodes.value.join('\n')).then(() => ui.toast('Codes copiés.'));
}

function downloadCodes() {
  const text = `Codes de secours — ${auth.user?.email ?? auth.user?.name}\nChaque code ne fonctionne qu’une fois.\n\n${recoveryCodes.value.join('\n')}\n`;
  const url = URL.createObjectURL(new Blob([text], { type: 'text/plain;charset=utf-8' }));
  Object.assign(document.createElement('a'), { href: url, download: 'codes-de-secours.txt' }).click();
  URL.revokeObjectURL(url);
}

async function continueIfSecured() {
  if (auth.securityAction) return;
  await auth.afterLogin();
  if (forcedFlow) router.push(auth.homePath);
}

async function finishRecovery() {
  recoveryCodes.value = [];
  await continueIfSecured();
}
</script>

<template>
  <div class="mx-auto max-w-3xl space-y-6">
    <PageHeader title="Sécurité du compte" subtitle="Mot de passe et double authentification." />

    <p v-if="forcedPassword" class="flex gap-3 rounded-xl bg-warn-50 p-4 text-sm text-warn-700" role="alert">
      <AppIcon name="lock" class="mt-0.5 size-4 shrink-0" />
      <span>Le mot de passe reçu par e-mail est <b>provisoire</b>. Choisissez votre propre mot de passe pour accéder à l’application.</span>
    </p>
    <p v-else-if="forcedTwoFactor" class="flex gap-3 rounded-xl bg-warn-50 p-4 text-sm text-warn-700" role="alert">
      <AppIcon name="shield" class="mt-0.5 size-4 shrink-0" />
      <span>La <b>double authentification</b> est obligatoire pour votre compte. Activez-la pour continuer.</span>
    </p>

    <!-- Mot de passe -->
    <form class="card space-y-4 p-5" novalidate @submit.prevent="savePassword">
      <div>
        <h2 class="card-title">{{ forcedPassword ? 'Choisir mon mot de passe' : 'Mot de passe' }}</h2>
        <p class="mt-1 text-sm text-muted">Au moins {{ minLength }} caractères. Les autres appareils connectés seront déconnectés.</p>
      </div>
      <div class="grid gap-4 sm:grid-cols-3">
        <div class="flex flex-col gap-1">
          <label for="s-cur" class="label">{{ forcedPassword ? 'Mot de passe provisoire' : 'Mot de passe actuel' }}</label>
          <input id="s-cur" v-model="pass.current" type="password" class="input" autocomplete="current-password" :aria-invalid="!!passErrors.current" />
          <p v-if="passErrors.current" class="text-xs text-danger-600">{{ passErrors.current }}</p>
        </div>
        <div class="flex flex-col gap-1">
          <label for="s-new" class="label">Nouveau</label>
          <input id="s-new" v-model="pass.password" type="password" class="input" autocomplete="new-password" :aria-invalid="!!passErrors.password" />
          <p v-if="passErrors.password" class="text-xs text-danger-600">{{ passErrors.password }}</p>
        </div>
        <div class="flex flex-col gap-1">
          <label for="s-conf" class="label">Confirmation</label>
          <input id="s-conf" v-model="pass.password_confirmation" type="password" class="input" autocomplete="new-password" :aria-invalid="!!passErrors.password_confirmation" />
          <p v-if="passErrors.password_confirmation" class="text-xs text-danger-600">{{ passErrors.password_confirmation }}</p>
        </div>
      </div>
      <div class="flex justify-end"><button type="submit" class="btn btn-primary" :disabled="savingPass">{{ savingPass ? 'Enregistrement…' : 'Enregistrer le mot de passe' }}</button></div>
    </form>

    <!-- Double authentification -->
    <section v-if="security.two_factor_available" class="card space-y-4 p-5" :class="forcedPassword ? 'opacity-60' : ''">
      <div class="flex flex-wrap items-start gap-3">
        <div class="min-w-0 flex-1">
          <h2 class="card-title">Double authentification</h2>
          <p class="mt-1 text-sm text-muted">À chaque connexion, en plus du mot de passe, un code à 6 chiffres est demandé. Il est généré par une application sur votre téléphone (Google Authenticator, Microsoft Authenticator, Authy…).</p>
        </div>
        <StatusBadge :status="security.two_factor_enabled ? 'active' : 'inactive'" />
      </div>

      <p v-if="forcedPassword" class="text-sm text-subtle">Choisissez d’abord votre mot de passe.</p>

      <!-- Codes de secours affichés une seule fois -->
      <div v-else-if="recoveryCodes.length" class="space-y-3 rounded-xl bg-mint p-4">
        <p class="text-sm"><b>Notez ces codes de secours</b> et gardez-les en lieu sûr : ils permettent de vous connecter si vous perdez votre téléphone. Chacun ne fonctionne qu’une fois et ils ne seront plus affichés.</p>
        <ul class="grid grid-cols-2 gap-2 font-mono text-sm sm:grid-cols-4">
          <li v-for="c in recoveryCodes" :key="c" class="rounded-lg bg-white px-3 py-2 text-center ring-1 ring-line">{{ c }}</li>
        </ul>
        <div class="flex flex-wrap gap-2">
          <button type="button" class="btn btn-secondary" @click="copyCodes"><AppIcon name="copy" class="size-4" />Copier</button>
          <button type="button" class="btn btn-secondary" @click="downloadCodes"><AppIcon name="download" class="size-4" />Télécharger</button>
          <button type="button" class="btn btn-primary ml-auto" @click="finishRecovery">J’ai noté mes codes</button>
        </div>
      </div>

      <!-- Activation : QR code puis premier code -->
      <div v-else-if="setup" class="grid gap-5 sm:grid-cols-[220px_1fr]">
        <img :src="setup.qr" alt="QR code à scanner avec l’application d’authentification" class="size-[220px] rounded-lg ring-1 ring-line" />
        <form class="space-y-3" novalidate @submit.prevent="confirmSetup">
          <ol class="list-decimal space-y-1 pl-5 text-sm text-body">
            <li>Ouvrez l’application d’authentification et scannez le QR code.</li>
            <li>Ou saisissez cette clé : <code class="rounded bg-ground px-1.5 py-0.5 font-mono text-[13px]">{{ setup.secret }}</code></li>
            <li>Entrez le code à 6 chiffres affiché.</li>
          </ol>
          <div class="flex flex-col gap-1">
            <label for="s-code" class="label">Code de vérification</label>
            <input id="s-code" v-model="code" class="input w-40 text-center font-mono text-lg tracking-[0.3em]" inputmode="numeric" autocomplete="one-time-code" maxlength="7" :aria-invalid="!!codeError" />
            <p v-if="codeError" class="text-xs text-danger-600">{{ codeError }}</p>
          </div>
          <div class="flex gap-2">
            <button type="submit" class="btn btn-primary" :disabled="busy">Activer</button>
            <button type="button" class="btn btn-ghost" @click="setup = null">Annuler</button>
          </div>
        </form>
      </div>

      <div v-else-if="!security.two_factor_enabled">
        <button type="button" class="btn btn-primary" :disabled="busy" @click="startSetup"><AppIcon name="shield" class="size-4" />Activer la double authentification</button>
      </div>

      <!-- Active : codes de secours, désactivation -->
      <div v-else class="space-y-3">
        <p class="text-sm text-muted">Codes de secours restants : <b>{{ security.recovery_codes_left }}</b> / 8.</p>
        <form v-if="passwordAction" class="flex flex-wrap items-end gap-2 rounded-xl bg-ground p-3" novalidate @submit.prevent="runPasswordAction">
          <div class="flex flex-col gap-1">
            <label for="s-confirm" class="label">Votre mot de passe</label>
            <input id="s-confirm" v-model="confirmPassword" type="password" class="input" autocomplete="current-password" :aria-invalid="!!passwordError" />
          </div>
          <button type="submit" class="btn" :class="passwordAction === 'disable' ? 'btn-danger' : 'btn-primary'" :disabled="busy">{{ passwordAction === 'disable' ? 'Désactiver' : 'Générer de nouveaux codes' }}</button>
          <button type="button" class="btn btn-ghost" @click="passwordAction = ''; passwordError = ''">Annuler</button>
          <p v-if="passwordError" class="w-full text-xs text-danger-600">{{ passwordError }}</p>
        </form>
        <div v-else class="flex flex-wrap gap-2">
          <button type="button" class="btn btn-secondary" @click="passwordAction = 'regenerate'"><AppIcon name="refresh" class="size-4" />Nouveaux codes de secours</button>
          <button v-if="!security.two_factor_required" type="button" class="btn btn-ghost text-danger-600" @click="passwordAction = 'disable'">Désactiver</button>
          <span v-else class="self-center text-xs text-subtle">Obligatoire pour votre compte.</span>
        </div>
      </div>
    </section>
  </div>
</template>
