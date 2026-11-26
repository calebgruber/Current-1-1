<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';

const props = withDefaults(
    defineProps<{
        variant?: 'primary' | 'secondary' | 'ghost' | 'danger' | 'success';
        size?: 'xs' | 'sm' | 'md' | 'lg' | 'xl';
        href?: string;
        method?: 'get' | 'post' | 'put' | 'patch' | 'delete';
        type?: 'button' | 'submit' | 'reset';
        loading?: boolean;
        disabled?: boolean;
        block?: boolean;
    }>(),
    { variant: 'secondary', size: 'sm', type: 'button' },
);

const classes = computed(() => [
    'inline-flex items-center justify-center gap-1.5 rounded-md font-medium whitespace-nowrap transition select-none disabled:cursor-not-allowed disabled:opacity-50',
    {
        primary: 'bg-accent text-white shadow-xs hover:bg-accent-hover dark:text-zinc-950',
        secondary: 'border border-line bg-surface text-ink shadow-xs hover:bg-hover',
        ghost: 'text-muted hover:bg-hover hover:text-ink',
        danger: 'bg-red-600 text-white shadow-xs hover:bg-red-700',
        success: 'bg-emerald-600 text-white shadow-xs hover:bg-emerald-700',
    }[props.variant],
    {
        xs: 'h-6 px-2 text-xs',
        sm: 'h-8 px-3 text-sm',
        md: 'h-9 px-3.5 text-sm',
        lg: 'h-11 px-5 text-base',
        xl: 'h-16 px-8 text-xl',
    }[props.size],
    props.block ? 'w-full' : '',
]);
</script>

<template>
    <Link v-if="href && !disabled" :href="href" :method="method ?? 'get'" :as="method && method !== 'get' ? 'button' : 'a'" :class="classes">
        <slot />
    </Link>
    <button v-else :type="type" :class="classes" :disabled="disabled || loading" :aria-busy="loading || undefined">
        <LoaderCircle v-if="loading" class="size-4 animate-spin" aria-hidden="true" />
        <slot />
    </button>
</template>
