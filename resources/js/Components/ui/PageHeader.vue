<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ChevronRight } from 'lucide-vue-next';

defineProps<{ title: string; description?: string; breadcrumbs?: { label: string; href?: string }[] }>();
</script>

<template>
    <Head :title="title" />
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div class="min-w-0">
            <nav v-if="breadcrumbs?.length" aria-label="Breadcrumb" class="mb-1 flex items-center gap-1 text-xs text-muted">
                <template v-for="(crumb, i) in breadcrumbs" :key="i">
                    <Link v-if="crumb.href" :href="crumb.href" class="hover:text-ink">{{ crumb.label }}</Link>
                    <span v-else>{{ crumb.label }}</span>
                    <ChevronRight class="size-3" aria-hidden="true" />
                </template>
            </nav>
            <h1 class="truncate text-xl font-semibold tracking-tight">
                <slot name="title">{{ title }}</slot>
            </h1>
            <p v-if="description || $slots.description" class="mt-1 text-sm text-muted">
                <slot name="description">{{ description }}</slot>
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2"><slot name="actions" /></div>
    </div>
</template>
