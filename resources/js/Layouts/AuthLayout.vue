<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import Toaster from '@/Components/app/Toaster.vue';
import ThemeToggle from '@/Components/app/ThemeToggle.vue';
import type { SharedProps } from '@/types';

const page = usePage<SharedProps>();
</script>

<template>
    <div class="flex min-h-screen flex-col items-center justify-center px-4 py-12">
        <div class="absolute top-4 right-4"><ThemeToggle /></div>
        <div class="mb-8 flex items-center gap-2 text-lg font-semibold">
            <span class="grid size-8 place-items-center rounded-lg bg-accent text-sm font-bold text-white dark:text-zinc-950">ST</span>
            {{ page.props.app?.organization || page.props.app?.name || 'StageTrack' }}
        </div>
        <main class="w-full max-w-sm">
            <slot />
        </main>
        <Toaster />
    </div>
</template>
