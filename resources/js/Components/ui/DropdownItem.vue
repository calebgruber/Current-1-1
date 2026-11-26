<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

defineProps<{ href?: string; method?: 'get' | 'post' | 'delete'; danger?: boolean }>();
</script>

<template>
    <component
        :is="href ? Link : 'button'"
        :href="href"
        :method="method"
        :as="href && method && method !== 'get' ? 'button' : undefined"
        :type="href ? undefined : 'button'"
        role="menuitem"
        :class="['flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-sm hover:bg-hover', danger ? 'text-red-600 dark:text-red-400' : 'text-ink']"
    >
        <slot />
    </component>
</template>
