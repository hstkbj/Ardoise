<script setup>
/**
 * Code d'accès d'un parent : affichage, copie, impression de la fiche à
 * remettre, régénération (l'ancien code est alors désactivé).
 */
import { ref } from 'vue';
import AppIcon from '@/components/ui/AppIcon.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import { parentsApi } from '@/services/api';
import { useUiStore } from '@/stores/ui';
import { useAuthStore } from '@/stores/auth';
import { formatDateTime } from '@/utils/format';

const props = defineProps({ parent: { type: Object, required: true } });
const emit = defineEmits(['updated']);
const ui = useUiStore();
const auth = useAuthStore();
const busy = ref(false);

async function copy() {
  try {
    await navigator.clipboard.writeText(props.parent.access_code);
    ui.toast('Code copié.');
  } catch {
    ui.toast('Copie impossible : sélectionnez le code manuellement.', { tone: 'info' });
  }
}

async function regenerate() {
  const ok = await ui.confirm({
    title: 'Générer un nouveau code ?',
    message: 'L’ancien code ne fonctionnera plus et le parent sera déconnecté de ses appareils. Remettez-lui le nouveau code.',
    confirmLabel: 'Générer un nouveau code',
    danger: true,
  });
  if (!ok) return;
  busy.value = true;
  try {
    emit('updated', await parentsApi.regenerateCode(props.parent.id));
    ui.toast('Nouveau code généré.');
  } catch (e) {
    ui.error(e);
  } finally {
    busy.value = false;
  }
}

const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);

function printSlip() {
  const school = esc(auth.user?.tenant?.name);
  const children = esc(props.parent.children);
  const win = window.open('', '_blank', 'width=600,height=700');
  if (!win) return ui.toast('Autorisez les fenêtres pour imprimer la fiche.', { tone: 'info' });
  win.document.write(`<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"><title>Fiche d'accès parent</title>
    <style>body{font-family:Arial,sans-serif;padding:32px;color:#15171C}h1{font-size:18px;margin:0 0 4px}.code{font:700 30px/1.2 monospace;letter-spacing:3px;border:2px dashed #1D5C4D;padding:18px;text-align:center;margin:24px 0;border-radius:12px}p{font-size:14px;line-height:1.5}.muted{color:#4F5560}</style>
    </head><body><h1>${school}</h1><p class="muted">Espace parent — fiche d'accès personnelle</p>
    <p><strong>${esc(props.parent.full_name)}</strong><br>Enfant(s) : ${children}</p>
    <div class="code">${esc(props.parent.access_code)}</div>
    <p>1. Ouvrez l'application ou le site de l'école.<br>2. Choisissez « Parent » puis saisissez ce code.<br>3. Vous accédez aux notes, absences, devoirs et paiements de vos enfants.</p>
    <p class="muted">Ce code est personnel : ne le communiquez pas. En cas de perte, demandez un nouveau code à l'établissement.</p>
    <script>window.onload=()=>{window.print()}<\/script></body></html>`);
  win.document.close();
}
</script>

<template>
  <section class="card p-5">
    <div class="flex items-start justify-between gap-3">
      <div>
        <h2 class="card-title">Code d’accès parent</h2>
        <p class="mt-0.5 text-sm text-muted">Le parent se connecte uniquement avec ce code, sans mot de passe.</p>
      </div>
      <StatusBadge :status="parent.status" :label="parent.status === 'active' ? 'Utilisé' : parent.status === 'pending' ? 'Jamais utilisé' : 'Désactivé'" />
    </div>

    <div v-if="parent.access_code" class="mt-4 flex items-center justify-between gap-3 rounded-xl border-2 border-dashed border-brand-300 bg-brand-50 px-4 py-3">
      <code class="font-mono text-xl font-bold tracking-[0.15em] text-brand-800 sm:text-2xl">{{ parent.access_code }}</code>
      <button type="button" class="btn btn-ghost btn-icon shrink-0" aria-label="Copier le code" @click="copy"><AppIcon name="copy" class="size-5" /></button>
    </div>
    <p v-else class="mt-4 rounded-lg bg-ground px-4 py-3 text-sm text-muted">Aucun code actif.</p>
    <p v-if="parent.access_code_generated_at" class="mt-2 text-xs text-subtle">Généré le {{ formatDateTime(parent.access_code_generated_at) }}</p>

    <div class="mt-4 flex flex-wrap gap-2">
      <button v-if="parent.access_code" type="button" class="btn btn-primary" @click="printSlip"><AppIcon name="printer" class="size-4" />Imprimer la fiche</button>
      <button type="button" class="btn btn-secondary" :disabled="busy" @click="regenerate"><AppIcon name="refresh" class="size-4" />Nouveau code</button>
    </div>
  </section>
</template>
