<script setup lang="ts">
import type { Option } from '@/types';

const model = defineModel<string | number | null>();
defineProps<{ options: (Option | string)[]; placeholder?: string; invalid?: boolean }>();

const valueOf = (o: Option | string) => (typeof o === 'string' ? o : o.value);
const labelOf = (o: Option | string) => (typeof o === 'string' ? o : o.label);
</script>

<template>
    <select v-model="model" :class="['st-input pr-8', invalid ? 'border-red-500' : '']" :aria-invalid="invalid || undefined">
        <option v-if="placeholder !== undefined" :value="null">{{ placeholder }}</option>
        <option v-for="o in options" :key="String(valueOf(o))" :value="valueOf(o)">{{ labelOf(o) }}</option>
    </select>
</template>
