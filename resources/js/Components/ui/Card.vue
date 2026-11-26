<script setup lang="ts">
defineProps<{ title?: string; description?: string; padded?: boolean }>();
</script>

<template>
    <section class="st-card">
        <header v-if="title || $slots.actions" class="flex items-center justify-between gap-3 border-b border-line px-4 py-3">
            <div>
                <h2 v-if="title" class="text-sm font-semibold">{{ title }}</h2>
                <p v-if="description" class="mt-0.5 text-xs text-muted">{{ description }}</p>
            </div>
            <div class="flex items-center gap-2"><slot name="actions" /></div>
        </header>
        <div :class="padded === false ? '' : 'p-4'"><slot /></div>
        <footer v-if="$slots.footer" class="border-t border-line px-4 py-3"><slot name="footer" /></footer>
    </section>
</template>
