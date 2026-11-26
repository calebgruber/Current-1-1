<script setup lang="ts">
import { watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { CircleAlert, CircleCheck, TriangleAlert, X } from 'lucide-vue-next';
import { useToast } from '@/composables/useToast';
import type { SharedProps } from '@/types';

const { toasts, push, dismiss } = useToast();
const page = usePage<SharedProps>();

watch(
    () => page.props.flash,
    (flash) => {
        if (flash?.success) push(flash.success, 'success');
        if (flash?.warning) push(flash.warning, 'warning');
        if (flash?.error) push(flash.error, 'error');
    },
    { immediate: true, deep: true },
);
</script>

<template>
    <div class="pointer-events-none fixed right-4 bottom-4 z-[60] flex w-80 flex-col gap-2" aria-live="polite" role="status">
        <TransitionGroup enter-from-class="translate-y-2 opacity-0" leave-to-class="opacity-0" enter-active-class="transition" leave-active-class="transition">
            <div v-for="t in toasts" :key="t.id" class="pointer-events-auto flex items-start gap-2.5 rounded-lg border border-line bg-surface p-3 text-sm shadow-lg">
                <CircleCheck v-if="t.level === 'success'" class="mt-0.5 size-4 shrink-0 text-emerald-500" />
                <TriangleAlert v-else-if="t.level === 'warning'" class="mt-0.5 size-4 shrink-0 text-amber-500" />
                <CircleAlert v-else class="mt-0.5 size-4 shrink-0 text-red-500" />
                <p class="flex-1">{{ t.message }}</p>
                <button type="button" class="text-faint hover:text-ink" aria-label="Dismiss" @click="dismiss(t.id)"><X class="size-3.5" /></button>
            </div>
        </TransitionGroup>
    </div>
</template>
