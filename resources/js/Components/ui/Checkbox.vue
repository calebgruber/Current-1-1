<script setup lang="ts">
const model = defineModel<boolean | unknown[]>();
defineProps<{ label?: string; description?: string; value?: unknown }>();
</script>

<template>
    <label class="flex cursor-pointer items-start gap-2.5">
        <input v-model="model" type="checkbox" :value="value" class="mt-0.5 size-4 rounded border-line-strong accent-[var(--st-accent)]" />
        <span v-if="label || $slots.default" class="text-sm">
            <slot>{{ label }}</slot>
            <span v-if="description" class="block text-xs text-muted">{{ description }}</span>
        </span>
    </label>
</template>
