<script setup lang="ts">
import type { Component } from 'vue';
import type { Tone } from '@/types';

const props = withDefaults(defineProps<{ label: string; value: number | string; tone?: Tone; icon?: Component; hint?: string; active?: boolean; clickable?: boolean }>(), { tone: 'neutral' });
defineEmits<{ click: [] }>();

const valueTones: Record<Tone, string> = {
    neutral: 'text-ink',
    accent: 'text-accent',
    success: 'text-emerald-600 dark:text-emerald-400',
    warning: 'text-amber-600 dark:text-amber-400',
    danger: 'text-red-600 dark:text-red-400',
    info: 'text-sky-600 dark:text-sky-400',
};
void props;
</script>

<template>
    <component
        :is="clickable ? 'button' : 'div'"
        :type="clickable ? 'button' : undefined"
        :aria-pressed="clickable ? !!active : undefined"
        :class="['st-card flex flex-col items-start p-4 text-left transition', clickable ? 'cursor-pointer hover:border-line-strong hover:bg-hover/50' : '', active ? 'border-accent ring-2 ring-accent/20' : '']"
        @click="$emit('click')"
    >
        <span class="flex items-center gap-1.5 text-xs font-medium text-muted">
            <component :is="icon" v-if="icon" class="size-3.5" aria-hidden="true" />{{ label }}
        </span>
        <span :class="['st-num mt-1 text-2xl font-semibold tracking-tight', valueTones[tone]]">{{ value }}</span>
        <span v-if="hint" class="mt-0.5 text-xs text-faint">{{ hint }}</span>
    </component>
</template>
