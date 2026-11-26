<script setup lang="ts">
import { Monitor, Moon, Sun } from 'lucide-vue-next';
import { useTheme } from '@/composables/useTheme';

const { preference, cycle } = useTheme();
</script>

<template>
    <button type="button" class="rounded-md p-1.5 text-muted hover:bg-hover hover:text-ink" :aria-label="`Theme: ${preference}. Click to change.`" :title="`Theme: ${preference}`" @click="cycle">
        <Sun v-if="preference === 'light'" class="size-4" />
        <Moon v-else-if="preference === 'dark'" class="size-4" />
        <Monitor v-else class="size-4" />
    </button>
</template>
