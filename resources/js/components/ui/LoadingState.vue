<script setup>
defineProps({
  variant: { type: String, default: 'table' },
  rows: { type: Number, default: 6 },
  columns: { type: Number, default: 5 },
});
</script>

<template>
  <div aria-busy="true" aria-label="Chargement en cours">
    <span class="sr-only">Chargement…</span>
    <div v-if="variant === 'table'" class="divide-y divide-line-soft">
      <div v-for="r in rows" :key="r" class="flex items-center gap-6 px-5 py-4">
        <div class="flex flex-1 items-center gap-3">
          <div class="skeleton size-8 rounded-full" />
          <div class="flex-1 space-y-2"><div class="skeleton h-3 w-40 max-w-full" /><div class="skeleton h-2.5 w-24" /></div>
        </div>
        <div v-for="c in columns - 1" :key="c" class="skeleton hidden h-3 flex-1 md:block" />
      </div>
    </div>
    <div v-else-if="variant === 'cards'" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <div v-for="r in rows" :key="r" class="card space-y-3 p-5"><div class="skeleton h-3 w-24" /><div class="skeleton h-6 w-32" /><div class="skeleton h-2.5 w-20" /></div>
    </div>
    <div v-else-if="variant === 'detail'" class="card space-y-6 p-6">
      <div class="flex items-center gap-4">
        <div class="skeleton size-14 rounded-full" />
        <div class="space-y-2"><div class="skeleton h-4 w-48" /><div class="skeleton h-3 w-28" /></div>
      </div>
      <div class="grid gap-5 sm:grid-cols-2">
        <div v-for="r in rows" :key="r" class="space-y-2"><div class="skeleton h-2.5 w-20" /><div class="skeleton h-3.5 w-40" /></div>
      </div>
    </div>
    <div v-else class="space-y-3">
      <div v-for="r in rows" :key="r" class="skeleton h-3" :style="{ width: `${90 - (r % 3) * 15}%` }" />
    </div>
  </div>
</template>
