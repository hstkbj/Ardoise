<script setup>
import { reactive, ref } from 'vue';
import AppIcon from '@/components/ui/AppIcon.vue';
import { publicApi } from '@/services/api';
import { useFormErrors } from '@/composables/useFormErrors';

const form = reactive({ name: '', role: '', school: '', city: '', email: '', phone: '', students: '', sites: 1, message: '' });
const { errors, validateRequired, setServerErrors } = useFormErrors();
const sending = ref(false);
const sent = ref(false);
const failure = ref('');

const fields = [
  { key: 'name', required: true },
  { key: 'school', required: true },
  { key: 'email', type: 'email', required: true },
  { key: 'phone', required: true },
];

async function submit() {
  failure.value = '';
  if (!validateRequired(fields, form)) return;
  sending.value = true;
  try {
    await publicApi.demoRequest({ ...form, sites: Number(form.sites) || null });
    sent.value = true;
  } catch (e) {
    setServerErrors(e.errors);
    failure.value = e.message;
  } finally {
    sending.value = false;
  }
}
</script>

<template>
  <section class="mx-auto grid max-w-6xl gap-12 px-5 pt-16 lg:grid-cols-[1fr_1.2fr]">
    <div>
      <h1 class="text-4xl font-semibold tracking-tight">Parlons de <span class="text-brand-600">votre école</span></h1>
      <p class="mt-5 text-lg text-muted">Laissez-nous vos coordonnées : nous vous présentons Ardoise avec les données d’une école de démonstration, puis nous préparons votre espace.</p>
      <ul class="mt-8 space-y-4 text-sm">
        <li class="flex items-center gap-3 rounded-2xl bg-mint p-4"><AppIcon name="clock" class="size-5 text-brand-600" />Réponse sous 24 h ouvrées</li>
        <li class="flex items-center gap-3 rounded-2xl bg-sun-soft p-4"><AppIcon name="users" class="size-5 text-warn-700" />Démonstration de 30 minutes, en ligne ou sur place</li>
        <li class="flex items-center gap-3 rounded-2xl bg-lavender p-4"><AppIcon name="shield" class="size-5 text-lavender-700" />Vos informations ne sont jamais revendues</li>
      </ul>
    </div>

    <div class="rounded-[28px] border border-line bg-white p-6 sm:p-8">
      <div v-if="sent" class="py-10 text-center" role="status">
        <span class="mx-auto flex size-14 items-center justify-center rounded-full bg-mint text-brand-600"><AppIcon name="check" class="size-7" /></span>
        <h2 class="mt-5 text-xl font-semibold">Merci, demande envoyée !</h2>
        <p class="mt-2 text-muted">Nous vous recontactons très rapidement.</p>
      </div>
      <form v-else class="grid gap-4 sm:grid-cols-2" novalidate @submit.prevent="submit">
        <div class="flex flex-col gap-1"><label for="c-name" class="label">Nom complet *</label><input id="c-name" v-model="form.name" class="input" autocomplete="name" :aria-invalid="!!errors.name" /><p v-if="errors.name" class="text-xs text-danger-600">{{ errors.name }}</p></div>
        <div class="flex flex-col gap-1"><label for="c-role" class="label">Fonction</label><input id="c-role" v-model="form.role" class="input" placeholder="Directeur, fondateur…" /></div>
        <div class="flex flex-col gap-1"><label for="c-school" class="label">Établissement *</label><input id="c-school" v-model="form.school" class="input" :aria-invalid="!!errors.school" /><p v-if="errors.school" class="text-xs text-danger-600">{{ errors.school }}</p></div>
        <div class="flex flex-col gap-1"><label for="c-city" class="label">Ville</label><input id="c-city" v-model="form.city" class="input" /></div>
        <div class="flex flex-col gap-1"><label for="c-email" class="label">E-mail *</label><input id="c-email" v-model="form.email" type="email" class="input" autocomplete="email" :aria-invalid="!!errors.email" /><p v-if="errors.email" class="text-xs text-danger-600">{{ errors.email }}</p></div>
        <div class="flex flex-col gap-1"><label for="c-phone" class="label">Téléphone *</label><input id="c-phone" v-model="form.phone" type="tel" class="input" autocomplete="tel" :aria-invalid="!!errors.phone" /><p v-if="errors.phone" class="text-xs text-danger-600">{{ errors.phone }}</p></div>
        <div class="flex flex-col gap-1">
          <label for="c-students" class="label">Nombre d’élèves</label>
          <select id="c-students" v-model="form.students" class="input"><option value="">—</option><option>Moins de 300</option><option>300 à 1 000</option><option>1 000 à 3 000</option><option>Plus de 3 000</option></select>
        </div>
        <div class="flex flex-col gap-1"><label for="c-sites" class="label">Nombre de sites</label><input id="c-sites" v-model="form.sites" type="number" min="1" class="input" /></div>
        <div class="flex flex-col gap-1 sm:col-span-2"><label for="c-msg" class="label">Message</label><textarea id="c-msg" v-model="form.message" rows="4" class="input" /></div>
        <p v-if="failure" class="text-sm text-danger-600 sm:col-span-2" role="alert">{{ failure }}</p>
        <button type="submit" class="btn btn-lg btn-primary rounded-full sm:col-span-2" :disabled="sending">{{ sending ? 'Envoi…' : 'Envoyer ma demande' }}</button>
      </form>
    </div>
  </section>
</template>
