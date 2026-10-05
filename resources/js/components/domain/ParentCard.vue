<script setup>
import Avatar from '@/components/ui/Avatar.vue';
import AppIcon from '@/components/ui/AppIcon.vue';

defineProps({ parent: { type: Object, required: true } });
</script>

<template>
  <div class="card p-4">
    <div class="flex items-center gap-3">
      <Avatar :name="parent.full_name" size="lg" />
      <div class="min-w-0">
        <p class="truncate font-semibold">{{ parent.full_name }}</p>
        <p class="truncate text-sm text-muted">{{ parent.profession || 'Parent / tuteur' }}</p>
      </div>
    </div>
    <ul class="mt-4 space-y-2 text-sm">
      <li v-if="parent.phone" class="flex items-center gap-2"><AppIcon name="phone" class="size-4 text-subtle" /><a :href="`tel:${parent.phone}`" class="link">{{ parent.phone }}</a></li>
      <li v-if="parent.email" class="flex items-center gap-2"><AppIcon name="mail" class="size-4 text-subtle" /><span class="truncate">{{ parent.email }}</span></li>
      <li v-if="parent.children" class="flex items-center gap-2"><AppIcon name="users" class="size-4 text-subtle" />{{ parent.children }}</li>
    </ul>
  </div>
</template>
