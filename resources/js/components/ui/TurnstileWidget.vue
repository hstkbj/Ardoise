<script setup>
/** Défi anti-robot Cloudflare Turnstile (affiché seulement si TURNSTILE_SITE_KEY est configurée). */
import { onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({ siteKey: { type: String, required: true } });
const model = defineModel({ type: String, default: '' });
const box = ref(null);
let widgetId = null;
let scriptPromise = null;

function loadScript() {
  if (window.turnstile) return Promise.resolve();
  scriptPromise ??= new Promise((resolve, reject) => {
    const el = document.createElement('script');
    el.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';
    el.async = true;
    el.onload = resolve;
    el.onerror = reject;
    document.head.appendChild(el);
  });
  return scriptPromise;
}

function reset() {
  model.value = '';
  if (widgetId !== null && window.turnstile) window.turnstile.reset(widgetId);
}

onMounted(async () => {
  try {
    await loadScript();
    widgetId = window.turnstile.render(box.value, {
      sitekey: props.siteKey,
      language: 'fr',
      callback: (token) => (model.value = token),
      'expired-callback': () => (model.value = ''),
      'error-callback': () => (model.value = ''),
    });
  } catch {
    // script bloqué : le serveur refusera l'envoi avec un message clair
  }
});

onBeforeUnmount(() => {
  if (widgetId !== null && window.turnstile) window.turnstile.remove(widgetId);
});

defineExpose({ reset });
</script>

<template>
  <div ref="box" class="min-h-[65px]" />
</template>
