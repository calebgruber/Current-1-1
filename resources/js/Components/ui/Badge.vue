<script setup lang="ts">
import type { Tone } from '@/types';

withDefaults(defineProps<{ tone?: Tone; dot?: boolean; size?: 'sm' | 'md' }>(), { tone: 'neutral', size: 'sm' });

const tones: Record<Tone, string> = {
    neutral: 'bg-subtle text-muted ring-line',
    accent: 'bg-accent-soft text-accent-ink ring-accent/30',
    success: 'bg-emerald-100 text-emerald-900 ring-emerald-600/30 dark:bg-emerald-950 dark:text-emerald-200',
    warning: 'bg-amber-100 text-amber-900 ring-amber-600/30 dark:bg-amber-950 dark:text-amber-200',
    danger: 'bg-red-100 text-red-900 ring-red-600/30 dark:bg-red-950 dark:text-red-200',
    info: 'bg-sky-100 text-sky-900 ring-sky-600/30 dark:bg-sky-950 dark:text-sky-200',
};
const dots: Record<Tone, string> = {
    neutral: 'bg-zinc-400',
    accent: 'bg-accent',
    success: 'bg-emerald-500',
    warning: 'bg-amber-500',
    danger: 'bg-red-500',
    info: 'bg-sky-500',
};
</script>

<template>
    <span :class="['inline-flex items-center gap-1 rounded-none font-medium whitespace-nowrap ring-1 ring-inset', tones[tone], size === 'sm' ? 'px-2 py-0.5 text-xs' : 'px-2.5 py-1 text-sm']">
        <span v-if="dot" :class="['size-1.5', dots[tone]]" aria-hidden="true" />
        <slot />
    </span>
</template>
