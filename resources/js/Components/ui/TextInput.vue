<script setup lang="ts">
import { onMounted, ref } from 'vue';

const model = defineModel<string | number | null>();
const props = defineProps<{ invalid?: boolean; autofocus?: boolean }>();
const input = ref<HTMLInputElement>();

onMounted(() => {
    if (props.autofocus) input.value?.focus();
});
defineExpose({ focus: () => input.value?.focus(), select: () => input.value?.select() });
</script>

<template>
    <input ref="input" v-model="model" :class="['st-input', invalid ? 'border-red-500 focus:border-red-500 focus:ring-red-500/20' : '']" :aria-invalid="invalid || undefined" />
</template>
