<script setup>
/** Aperçu illustratif du tableau de bord (site public). Données fictives d'illustration. */
const bars = [62, 74, 58, 81, 69, 88, 77];
</script>

<template>
  <div class="rounded-[28px] border border-line bg-white p-3 shadow-[0_30px_80px_-30px_rgba(29,92,77,0.35)]" aria-hidden="true">
    <div class="flex items-center gap-1.5 px-3 pb-3"><span class="size-2.5 rounded-full bg-line-strong" /><span class="size-2.5 rounded-full bg-line-strong" /><span class="size-2.5 rounded-full bg-line-strong" /></div>
    <div class="grid gap-3 rounded-[20px] bg-ground-soft p-4 sm:grid-cols-[150px_1fr]">
      <div class="hidden space-y-2 sm:block">
        <div v-for="(w, i) in [80, 64, 72, 56, 68, 60]" :key="i" class="flex items-center gap-2 rounded-lg px-2 py-1.5" :class="i === 0 ? 'bg-brand-100' : ''">
          <span class="size-3.5 rounded" :class="i === 0 ? 'bg-brand-600' : 'bg-line-strong'" />
          <span class="h-2 rounded bg-line-strong" :style="{ width: `${w}%` }" />
        </div>
      </div>
      <div class="space-y-3">
        <div class="grid grid-cols-3 gap-3">
          <div class="rounded-xl bg-mint p-3"><p class="text-[10px] text-muted">Élèves</p><p class="text-lg font-semibold">1 248</p></div>
          <div class="rounded-xl bg-sun-soft p-3"><p class="text-[10px] text-muted">Encaissé</p><p class="text-lg font-semibold">82 %</p></div>
          <div class="rounded-xl bg-lavender p-3"><p class="text-[10px] text-muted">Présence</p><p class="text-lg font-semibold">96 %</p></div>
        </div>
        <div class="rounded-xl border border-line bg-white p-3">
          <p class="mb-2 text-[11px] font-medium text-muted">Moyennes par niveau</p>
          <div class="flex h-24 items-end gap-2">
            <div v-for="(b, i) in bars" :key="i" class="flex-1 rounded-t-md" :class="i === 5 ? 'bg-brand-600' : 'bg-brand-200'" :style="{ height: `${b}%` }" />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
