<script setup lang="ts">
defineProps<{ label?: string; for?: string; error?: string | null; hint?: string; required?: boolean }>();
</script>

<template>
    <div>
        <label v-if="label" :for="$props.for" class="st-label">
            {{ label }}<span v-if="required" class="text-red-500" aria-hidden="true"> *</span>
        </label>
        <slot />
        <p v-if="error" class="mt-1 text-xs text-red-600 dark:text-red-400" role="alert">{{ error }}</p>
        <p v-else-if="hint" class="mt-1 text-xs text-faint">{{ hint }}</p>
    </div>
</template>
