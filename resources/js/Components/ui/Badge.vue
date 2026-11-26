<script setup lang="ts">
import type { Tone } from '@/types';

withDefaults(defineProps<{ tone?: Tone; dot?: boolean; size?: 'sm' | 'md' }>(), { tone: 'neutral', size: 'sm' });

const tones: Record<Tone, string> = {
    neutral: 'bg-subtle text-muted ring-line',
    accent: 'bg-accent-soft text-accent-ink ring-accent/20',
    success: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-300',
    warning: 'bg-amber-50 text-amber-800 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-300',
    danger: 'bg-red-50 text-red-700 ring-red-600/20 dark:bg-red-500/10 dark:text-red-300',
    info: 'bg-sky-50 text-sky-700 ring-sky-600/20 dark:bg-sky-500/10 dark:text-sky-300',
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
    <span :class="['inline-flex items-center gap-1 rounded-full font-medium whitespace-nowrap ring-1 ring-inset', tones[tone], size === 'sm' ? 'px-2 py-0.5 text-xs' : 'px-2.5 py-1 text-sm']">
        <span v-if="dot" :class="['size-1.5 rounded-full', dots[tone]]" aria-hidden="true" />
        <slot />
    </span>
</template>
