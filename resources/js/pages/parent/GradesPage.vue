<script setup>
import { ref } from 'vue';
import ParentSection from '@/components/domain/ParentSection.vue';
import { formatDate, formatScore } from '@/utils/format';

const subject = ref('');
const filtered = (child) => child.grades.filter((g) => !subject.value || g.subject === subject.value);
const subjects = (child) => [...new Set(child.grades.map((g) => g.subject))];
</script>

<template>
  <ParentSection title="Notes">
    <template #default="{ child }">
      <div class="flex gap-2 overflow-x-auto pb-1">
        <button type="button" class="h-8 shrink-0 rounded-full border px-3 text-[13px]" :class="!subject ? 'border-brand-600 bg-brand-600 text-white' : 'border-line bg-white'" @click="subject = ''">Toutes</button>
        <button v-for="s in subjects(child)" :key="s" type="button" class="h-8 shrink-0 rounded-full border px-3 text-[13px]" :class="subject === s ? 'border-brand-600 bg-brand-600 text-white' : 'border-line bg-white'" @click="subject = s">{{ s }}</button>
      </div>
      <div class="card">
        <p v-if="!filtered(child).length" class="p-6 text-center text-sm text-subtle">Aucune note publiée. Les notes apparaissent dès leur validation par l’enseignant.</p>
        <ul v-else class="divide-y divide-line-soft">
          <li v-for="g in filtered(child)" :key="g.id" class="px-5 py-3.5">
            <div class="flex items-center gap-3">
              <span class="min-w-0 flex-1"><span class="block truncate text-sm font-medium">{{ g.subject }} · {{ g.title }}</span><span class="text-xs text-subtle">{{ formatDate(g.date) }} · coef. {{ g.coefficient }}<template v-if="g.class_average != null"> · moyenne de classe {{ formatScore(g.class_average, 1) }}</template></span></span>
              <span class="text-lg font-semibold tabular" :class="g.score != null && g.score / g.max < 0.5 ? 'text-danger-600' : ''">{{ g.score == null ? 'Absent' : formatScore(g.score) }}<span v-if="g.score != null" class="text-xs font-normal text-subtle">/{{ g.max }}</span></span>
            </div>
            <p v-if="g.comment" class="mt-1 text-[13px] text-muted italic">« {{ g.comment }} »</p>
          </li>
        </ul>
      </div>
    </template>
  </ParentSection>
</template>
