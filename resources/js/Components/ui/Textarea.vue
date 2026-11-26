<script setup lang="ts">
const model = defineModel<string | null>();
defineProps<{ invalid?: boolean; rows?: number }>();
</script>

<template>
    <textarea v-model="model" :rows="rows ?? 4" :class="['st-input', invalid ? 'border-red-500' : '']" :aria-invalid="invalid || undefined" />
</template>
