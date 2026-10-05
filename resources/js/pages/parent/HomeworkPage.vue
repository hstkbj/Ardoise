<script setup>
import ParentSection from '@/components/domain/ParentSection.vue';
import { parentApi } from '@/services/api';
import { useUiStore } from '@/stores/ui';
import { formatDate, today } from '@/utils/format';

const ui = useUiStore();
async function toggle(h, child) {
  const done = !h.done;
  h.done = done;
  try {
    await parentApi.homeworkDone(h.id, child.id, done);
  } catch (e) {
    h.done = !done;
    ui.error(e);
  }
}
const late = (h) => !h.done && h.due < today();
</script>

<template>
  <ParentSection title="Devoirs">
    <template #default="{ child }">
      <div class="card">
        <p v-if="!child.homework.length" class="p-6 text-center text-sm text-subtle">Aucun devoir donné récemment.</p>
        <ul v-else class="divide-y divide-line-soft">
          <li v-for="h in child.homework" :key="h.id" class="flex gap-3 px-5 py-4">
            <input :id="`hw-${h.id}`" type="checkbox" class="mt-1 size-5 shrink-0 accent-brand-600" :checked="h.done" @change="toggle(h, child)" />
            <label :for="`hw-${h.id}`" class="min-w-0 flex-1 cursor-pointer">
              <span class="block text-sm font-medium" :class="h.done ? 'text-subtle line-through' : ''">{{ h.subject }} · {{ h.title }}</span>
              <span class="text-xs" :class="late(h) ? 'font-medium text-danger-600' : 'text-subtle'">Pour le {{ formatDate(h.due, { weekday: 'long', day: 'numeric', month: 'long' }) }}</span>
              <span v-if="h.instructions" class="mt-1 block text-[13px] whitespace-pre-line text-muted">{{ h.instructions }}</span>
            </label>
          </li>
        </ul>
      </div>
      <p class="text-center text-xs text-subtle">Cochez un devoir lorsque votre enfant l’a terminé : l’enseignant le voit.</p>
    </template>
  </ParentSection>
</template>
