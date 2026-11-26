<script setup lang="ts">
import { Search } from 'lucide-vue-next';

const model = defineModel<string | null>();
defineProps<{ placeholder?: string }>();
</script>

<template>
    <div class="relative">
        <Search class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-faint" aria-hidden="true" />
        <input v-model="model" type="search" :placeholder="placeholder ?? 'Search…'" class="st-input pl-8" :aria-label="placeholder ?? 'Search'" />
    </div>
</template>
