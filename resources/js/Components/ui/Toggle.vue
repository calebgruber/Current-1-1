<script setup lang="ts">
const model = defineModel<boolean>({ default: false });
defineProps<{ label?: string; description?: string; disabled?: boolean }>();
</script>

<template>
    <label class="flex cursor-pointer items-start justify-between gap-4">
        <span v-if="label" class="text-sm">
            {{ label }}
            <span v-if="description" class="block text-xs text-muted">{{ description }}</span>
        </span>
        <button
            type="button"
            role="switch"
            :aria-checked="model"
            :disabled="disabled"
            :class="['relative inline-flex h-5 w-9 shrink-0 rounded-full transition disabled:opacity-50', model ? 'bg-accent' : 'bg-line-strong']"
            @click="model = !model"
        >
            <span :class="['absolute top-0.5 size-4 rounded-full bg-white shadow transition', model ? 'left-4.5' : 'left-0.5']" />
        </button>
    </label>
</template>
