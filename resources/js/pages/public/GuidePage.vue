<script setup>
/**
 * Guide d'utilisation : un onglet par public (école, enseignants, parents,
 * et plateforme pour l'équipe Ardoise), sommaire, recherche et impression.
 * Contenu : config/guide.js.
 */
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AppIcon from '@/components/ui/AppIcon.vue';
import { GUIDE } from '@/config/guide';
import { useAuthStore, STAFF_ROLES } from '@/stores/auth';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();

const audiences = computed(() => GUIDE.filter((a) => !a.platformOnly || auth.isPlatform));

/** Public proposé par défaut selon le profil connecté. */
function defaultAudience() {
  if (auth.isPlatform) return 'plateforme';
  if (auth.hasRole(...STAFF_ROLES)) return 'ecole';
  if (auth.hasRole('teacher')) return 'enseignant';
  if (auth.hasRole('parent')) return 'parent';
  return 'ecole';
}

const current = ref(audiences.value.some((a) => a.key === route.query.pour) ? route.query.pour : defaultAudience());
const audience = computed(() => audiences.value.find((a) => a.key === current.value) ?? audiences.value[0]);
const query = ref('');
const activeSection = ref('');

watch(current, (key) => {
  query.value = '';
  router.replace({ query: { ...route.query, pour: key }, hash: '' });
  window.scrollTo({ top: 0 });
});

/** Recherche insensible aux accents et à la casse. */
const normalize = (s) => String(s).normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
const sections = computed(() => {
  const q = normalize(query.value.trim());
  if (!q) return audience.value.sections;
  return audience.value.sections.filter((s) =>
    normalize([s.title, s.intro, ...(s.where ?? []), ...(s.steps ?? []), ...(s.tips ?? []).map((t) => t.text)].join(' ')).includes(q),
  );
});
const faq = computed(() => {
  const q = normalize(query.value.trim());
  return q ? audience.value.faq.filter(([question, answer]) => normalize(`${question} ${answer}`).includes(q)) : audience.value.faq;
});

/** Mise en forme légère : **gras** et `code` (le texte est échappé d'abord). */
function rich(text) {
  return String(text)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/\*\*(.+?)\*\*/g, '<strong class="font-semibold text-ink">$1</strong>')
    .replace(/`(.+?)`/g, '<code class="rounded bg-ground px-1.5 py-0.5 font-mono text-[0.85em] text-ink">$1</code>');
}

const TIP = {
  info: { icon: 'info', cls: 'bg-info-50 text-info-700' },
  warning: { icon: 'alert', cls: 'bg-warn-50 text-warn-700' },
  success: { icon: 'check-circle', cls: 'bg-mint text-brand-700' },
};

function goTo(id) {
  document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  history.replaceState(null, '', `#${id}`);
}

// Section visible : surlignée dans le sommaire
let observer;
function observe() {
  observer?.disconnect();
  observer = new IntersectionObserver(
    (entries) => {
      const visible = entries.filter((e) => e.isIntersecting).sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top)[0];
      if (visible) activeSection.value = visible.target.id;
    },
    { rootMargin: '-90px 0px -60% 0px' },
  );
  document.querySelectorAll('[data-guide-section]').forEach((el) => observer.observe(el));
}
watch(sections, (list) => {
  activeSection.value = list[0]?.id ?? '';
  nextTick(observe);
});
onMounted(() => {
  activeSection.value = sections.value[0]?.id ?? '';
  observe();
  if (route.hash) nextTick(() => document.getElementById(route.hash.slice(1))?.scrollIntoView());
});

const backTo = computed(() => (auth.isAuthenticated ? auth.homePath : null));
const print = () => window.print();
</script>

<template>
  <!-- En-tête -->
  <section class="bg-gradient-to-b from-cream to-white print:bg-none">
    <div class="mx-auto max-w-6xl px-5 pt-12 pb-8 sm:pt-16">
      <div class="flex flex-wrap items-center justify-between gap-3 print:hidden">
        <RouterLink v-if="backTo" :to="backTo" class="inline-flex items-center gap-1.5 text-sm font-medium text-brand-700 hover:text-brand-800"><AppIcon name="chevron-left" class="size-4" />Retour à mon espace</RouterLink>
        <span v-else />
        <button type="button" class="btn btn-secondary rounded-full" @click="print"><AppIcon name="printer" class="size-4" />Imprimer</button>
      </div>
      <h1 class="mt-6 text-4xl font-semibold tracking-tight sm:text-5xl">Guide <span class="text-brand-600">d’utilisation</span></h1>
      <p class="mt-4 max-w-2xl text-lg text-muted">Choisissez votre profil : chaque tâche est expliquée pas à pas, avec l’endroit où la trouver dans les menus.</p>

      <div class="mt-8 grid gap-3 sm:grid-cols-2 print:hidden" :class="audiences.length > 3 ? 'lg:grid-cols-4' : 'lg:grid-cols-3'" role="tablist" aria-label="Profil">
        <button
          v-for="a in audiences"
          :key="a.key"
          type="button"
          role="tab"
          :aria-selected="current === a.key"
          class="flex items-center gap-3 rounded-[22px] p-3 text-left transition sm:items-start sm:p-4"
          :class="[a.tint, current === a.key ? 'ring-2 ring-brand-600' : 'opacity-80 hover:opacity-100']"
          @click="current = a.key"
        >
          <span class="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-white shadow-sm" :class="a.accent"><AppIcon :name="a.icon" class="size-5" /></span>
          <span class="min-w-0">
            <span class="block font-semibold">{{ a.label }}</span>
            <span class="mt-0.5 hidden text-xs text-muted sm:block">{{ a.who }}</span>
          </span>
        </button>
      </div>
    </div>
  </section>

  <!-- Contenu -->
  <div class="mx-auto grid max-w-6xl gap-10 px-5 pb-10 lg:grid-cols-[240px_1fr]">
    <aside class="print:hidden">
      <div class="sticky top-24 space-y-4">
        <div class="relative">
          <AppIcon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-subtle" />
          <input v-model="query" type="search" class="input rounded-full pl-9" placeholder="Rechercher dans le guide…" aria-label="Rechercher dans le guide" />
        </div>
        <nav aria-label="Sommaire" class="hidden lg:block">
          <p class="px-3 pb-2 text-[11px] font-semibold tracking-wider text-subtle uppercase">Sommaire</p>
          <ul class="space-y-0.5">
            <li v-for="s in sections" :key="s.id">
              <button type="button" class="flex w-full items-center gap-2.5 rounded-lg px-3 py-1.5 text-left text-[13.5px]" :class="activeSection === s.id ? 'bg-brand-100 font-medium text-brand-700' : 'text-body hover:bg-ground'" @click="goTo(s.id)">
                <AppIcon :name="s.icon" class="size-4 shrink-0 opacity-70" />{{ s.title }}
              </button>
            </li>
            <li v-if="faq.length">
              <button type="button" class="flex w-full items-center gap-2.5 rounded-lg px-3 py-1.5 text-left text-[13.5px]" :class="activeSection === 'faq' ? 'bg-brand-100 font-medium text-brand-700' : 'text-body hover:bg-ground'" @click="goTo('faq')"><AppIcon name="help" class="size-4 opacity-70" />Questions fréquentes</button>
            </li>
          </ul>
        </nav>
      </div>
    </aside>

    <main class="min-w-0">
      <div class="rounded-[22px] p-5 sm:p-6" :class="audience.tint">
        <p class="text-xs font-semibold tracking-wider uppercase" :class="audience.accent">{{ audience.label }}</p>
        <p class="mt-1.5 text-[15px] text-body">{{ audience.intro }}</p>
      </div>

      <p v-if="query && !sections.length && !faq.length" class="mt-10 text-center text-muted" role="status">Aucun résultat pour « {{ query }} ».</p>

      <article v-for="(s, i) in sections" :id="s.id" :key="s.id" data-guide-section class="scroll-mt-24 border-b border-line-soft py-9 last:border-0 print:break-inside-avoid">
        <div class="flex items-start gap-4">
          <span class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-brand-50 text-brand-600"><AppIcon :name="s.icon" class="size-5" /></span>
          <div class="min-w-0 flex-1">
            <p class="text-xs font-medium text-subtle tabular">{{ String(i + 1).padStart(2, '0') }}</p>
            <h2 class="text-xl font-semibold tracking-tight">{{ s.title }}</h2>
            <p v-if="s.where?.length" class="mt-2 flex flex-wrap items-center gap-1 text-[12.5px]">
              <span class="mr-1 text-subtle">Où :</span>
              <template v-for="(w, wi) in s.where" :key="w">
                <span class="rounded-md bg-ground px-2 py-0.5 font-medium text-body">{{ w }}</span>
                <AppIcon v-if="wi < s.where.length - 1" name="chevron-right" class="size-3.5 text-subtle" />
              </template>
            </p>
          </div>
        </div>

        <p v-if="s.intro" class="mt-4 text-[15px] text-body sm:pl-15" v-html="rich(s.intro)" />

        <ol class="mt-4 space-y-3 sm:pl-15">
          <li v-for="(step, si) in s.steps" :key="si" class="flex gap-3">
            <span class="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full bg-ink text-[12px] font-semibold text-white tabular">{{ si + 1 }}</span>
            <span class="text-[15px] leading-relaxed text-body" v-html="rich(step)" />
          </li>
        </ol>

        <div v-for="(tip, ti) in s.tips ?? []" :key="ti" class="mt-4 flex gap-3 rounded-xl px-4 py-3 text-sm sm:ml-15" :class="TIP[tip.tone]?.cls">
          <AppIcon :name="TIP[tip.tone]?.icon ?? 'info'" class="mt-0.5 size-4 shrink-0" />
          <p v-html="rich(tip.text)" />
        </div>
      </article>

      <section v-if="faq.length" id="faq" data-guide-section class="scroll-mt-24 pt-9">
        <h2 class="text-xl font-semibold tracking-tight">Questions fréquentes</h2>
        <div class="mt-5 divide-y divide-line rounded-[22px] border border-line">
          <details v-for="([q, a], fi) in faq" :key="fi" class="group px-5 py-4" :open="!!query">
            <summary class="flex cursor-pointer list-none items-center justify-between gap-3 font-medium marker:hidden">
              {{ q }}
              <AppIcon name="chevron-down" class="size-4 shrink-0 text-subtle transition group-open:rotate-180" />
            </summary>
            <p class="mt-2 text-sm text-muted" v-html="rich(a)" />
          </details>
        </div>
      </section>

      <div class="mt-12 flex flex-wrap items-center justify-between gap-4 rounded-[22px] bg-mist p-6 print:hidden">
        <div>
          <p class="font-semibold">Besoin d’aide supplémentaire ?</p>
          <p class="text-sm text-muted">{{ auth.hasRole(...STAFF_ROLES) ? 'Écrivez à notre équipe depuis Administration → Support.' : current === 'parent' ? 'Contactez le secrétariat de l’établissement.' : 'Contactez-nous, nous répondons sous 24 h ouvrées.' }}</p>
        </div>
        <RouterLink :to="auth.hasRole(...STAFF_ROLES) ? '/admin/support' : '/contact'" class="btn btn-primary rounded-full">{{ auth.hasRole(...STAFF_ROLES) ? 'Ouvrir le support' : 'Nous contacter' }}</RouterLink>
      </div>
    </main>
  </div>
</template>
