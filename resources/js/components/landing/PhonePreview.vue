<script setup>
/** Aperçu illustratif de l'application parent (site public). */
</script>

<template>
  <div class="mx-auto w-[230px] rounded-[36px] border-[6px] border-ink bg-cream p-3 shadow-xl" aria-hidden="true">
    <div class="mx-auto mb-3 h-1.5 w-16 rounded-full bg-ink/80" />
    <p class="text-[11px] text-muted">Bonjour Mariam</p>
    <p class="text-sm font-semibold">Awa · 5e B</p>
    <div class="mt-3 rounded-2xl bg-white p-3">
      <p class="text-[10px] text-muted">Moyenne du trimestre</p>
      <p class="text-2xl font-bold">14,6<span class="text-xs text-subtle">/20</span></p>
      <p class="text-[10px] text-brand-600">5e sur 38</p>
    </div>
    <div class="mt-2 space-y-1.5">
      <div class="flex items-center justify-between rounded-xl bg-mint px-3 py-2 text-[11px]"><span>Maths · Devoir</span><b>16</b></div>
      <div class="flex items-center justify-between rounded-xl bg-sky px-3 py-2 text-[11px]"><span>Français · Dictée</span><b>13,5</b></div>
      <div class="flex items-center justify-between rounded-xl bg-blush px-3 py-2 text-[11px]"><span>Absence lundi</span><b>Justifiée</b></div>
    </div>
    <div class="mt-3 rounded-xl bg-brand-600 py-2 text-center text-[11px] font-medium text-white">Payer la scolarité</div>
  </div>
</template>
