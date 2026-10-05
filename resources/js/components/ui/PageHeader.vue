<script setup>
defineProps({
  title: { type: String, required: true },
  subtitle: { type: String, default: '' },
  breadcrumb: { type: Array, default: () => [] },
});
</script>

<template>
  <div class="mb-6">
    <nav v-if="breadcrumb.length" aria-label="Fil d'Ariane" class="mb-2 flex flex-wrap items-center gap-1.5 text-[13px] text-subtle">
      <template v-for="(item, i) in breadcrumb" :key="i">
        <RouterLink v-if="item.to" :to="item.to" class="hover:text-ink">{{ item.label }}</RouterLink>
        <span v-else :aria-current="i === breadcrumb.length - 1 ? 'page' : undefined">{{ item.label }}</span>
        <span v-if="i < breadcrumb.length - 1" aria-hidden="true">/</span>
      </template>
    </nav>
    <div class="flex flex-wrap items-end justify-between gap-4">
      <div class="min-w-0">
        <h1 class="text-2xl font-semibold tracking-tight">{{ title }}</h1>
        <p v-if="subtitle || $slots.subtitle" class="mt-1 text-sm text-muted"><slot name="subtitle">{{ subtitle }}</slot></p>
      </div>
      <div v-if="$slots.actions" class="flex flex-wrap items-center gap-2 print:hidden"><slot name="actions" /></div>
    </div>
  </div>
</template>
