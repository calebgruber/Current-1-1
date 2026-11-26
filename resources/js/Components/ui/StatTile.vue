<script setup lang="ts">
import type { Component } from 'vue';
import type { Tone } from '@/types';

const props = withDefaults(defineProps<{ label: string; value: number | string; tone?: Tone; icon?: Component; hint?: string; active?: boolean; clickable?: boolean }>(), { tone: 'neutral' });
defineEmits<{ click: [] }>();

const valueTones: Record<Tone, string> = {
    neutral: 'text-ink',
    accent: 'text-accent',
    success: 'text-emerald-900 dark:text-emerald-200',
    warning: 'text-amber-900 dark:text-amber-200',
    danger: 'text-red-900 dark:text-red-200',
    info: 'text-sky-900 dark:text-sky-200',
};
const backgrounds: Record<Tone, string> = {
    neutral: 'bg-surface',
    accent: 'border-indigo-200 bg-indigo-50 dark:border-indigo-900 dark:bg-indigo-950',
    success: 'border-emerald-200 bg-emerald-50 dark:border-emerald-900 dark:bg-emerald-950',
    warning: 'border-amber-200 bg-amber-50 dark:border-amber-900 dark:bg-amber-950',
    danger: 'border-red-200 bg-red-50 dark:border-red-900 dark:bg-red-950',
    info: 'border-sky-200 bg-sky-50 dark:border-sky-900 dark:bg-sky-950',
};
void props;
</script>

<template>
    <component
        :is="clickable ? 'button' : 'div'"
        :type="clickable ? 'button' : undefined"
        :aria-pressed="clickable ? !!active : undefined"
        :class="['st-card flex flex-col items-start p-4 text-left transition', backgrounds[tone], clickable ? 'cursor-pointer hover:brightness-95 dark:hover:brightness-125' : '', active ? 'ring-2 ring-accent' : '']"
        @click="$emit('click')"
    >
        <span class="flex items-center gap-1.5 text-xs font-medium text-muted">
            <component :is="icon" v-if="icon" class="size-3.5" aria-hidden="true" />{{ label }}
        </span>
        <span :class="['st-num mt-1 text-2xl font-semibold tracking-tight', valueTones[tone]]">{{ value }}</span>
        <span v-if="hint" class="mt-0.5 text-xs text-muted">{{ hint }}</span>
    </component>
</template>
