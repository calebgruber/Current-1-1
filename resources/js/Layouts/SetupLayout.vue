<script setup lang="ts">
import { Check } from 'lucide-vue-next';
import ThemeToggle from '@/Components/app/ThemeToggle.vue';

defineProps<{ steps?: { key: string; label: string }[]; current?: string }>();
</script>

<template>
    <div class="flex min-h-screen flex-col">
        <header class="flex h-14 items-center justify-between border-b border-line px-6">
            <div class="flex items-center gap-2 font-semibold">
                <span class="grid size-7 place-items-center rounded-md bg-accent text-xs font-bold text-white dark:text-zinc-950">ST</span>
                StageTrack Enterprise · Setup
            </div>
            <ThemeToggle />
        </header>
        <div class="mx-auto flex w-full max-w-5xl flex-1 gap-10 px-6 py-10">
            <ol v-if="steps?.length" class="hidden w-56 shrink-0 space-y-1 md:block" aria-label="Setup steps">
                <li v-for="(step, i) in steps" :key="step.key" :aria-current="step.key === current ? 'step' : undefined" :class="['flex items-center gap-2.5 rounded-md px-2 py-1.5 text-sm', step.key === current ? 'bg-accent-soft font-medium text-accent-ink' : 'text-muted']">
                    <span :class="['grid size-5 place-items-center rounded-full text-[11px]', steps.findIndex((s) => s.key === current) > i ? 'bg-emerald-500 text-white' : 'border border-line-strong']">
                        <Check v-if="steps.findIndex((s) => s.key === current) > i" class="size-3" />
                        <template v-else>{{ i + 1 }}</template>
                    </span>
                    {{ step.label }}
                </li>
            </ol>
            <main class="min-w-0 flex-1"><slot /></main>
        </div>
    </div>
</template>
