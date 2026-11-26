<script setup lang="ts">
import type { Component } from 'vue';
import { Inbox } from 'lucide-vue-next';

defineProps<{ title: string; description?: string; icon?: Component }>();
</script>

<template>
    <div class="flex flex-col items-center justify-center px-6 py-14 text-center">
        <div class="mb-3 rounded-full bg-subtle p-3 text-muted"><component :is="icon ?? Inbox" class="size-6" aria-hidden="true" /></div>
        <h3 class="text-sm font-semibold">{{ title }}</h3>
        <p v-if="description" class="mt-1 max-w-sm text-sm text-muted">{{ description }}</p>
        <div v-if="$slots.default" class="mt-4"><slot /></div>
    </div>
</template>
