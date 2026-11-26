<script setup lang="ts">
const model = defineModel<string>();
defineProps<{ tabs: { key: string; label: string; count?: number | null }[] }>();
</script>

<template>
    <div role="tablist" class="flex gap-1 overflow-x-auto border-b border-line">
        <button
            v-for="tab in tabs"
            :key="tab.key"
            type="button"
            role="tab"
            :aria-selected="model === tab.key"
            :class="['-mb-px flex items-center gap-1.5 border-b-2 px-3 py-2 text-sm whitespace-nowrap transition', model === tab.key ? 'border-accent font-medium text-ink' : 'border-transparent text-muted hover:text-ink']"
            @click="model = tab.key"
        >
            {{ tab.label }}
            <span v-if="tab.count !== undefined && tab.count !== null" class="st-num rounded-full bg-subtle px-1.5 text-xs text-muted">{{ tab.count }}</span>
        </button>
    </div>
</template>
