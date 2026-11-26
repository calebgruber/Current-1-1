<script setup lang="ts">
import Modal from '@/Components/ui/Modal.vue';
import { navigation } from '@/lib/navigation';
import { isMac } from '@/composables/useHotkeys';

const open = defineModel<boolean>('open', { default: false });
const mod = isMac ? '⌘' : 'Ctrl';
const general = [
    { keys: [mod, 'K'], label: 'Search / command palette' },
    { keys: ['/'], label: 'Search' },
    { keys: ['?'], label: 'Show keyboard shortcuts' },
    { keys: [mod, 'J'], label: 'Toggle dark mode' },
    { keys: [mod, 'B'], label: 'Toggle sidebar' },
    { keys: ['C'], label: 'Create (on list pages)' },
];
const goto = navigation.flatMap((s) => s.items).filter((i) => i.shortcut);
</script>

<template>
    <Modal v-model:open="open" title="Keyboard shortcuts" size="lg">
        <div class="grid gap-6 sm:grid-cols-2">
            <div>
                <h3 class="mb-2 text-xs font-medium text-muted uppercase">General</h3>
                <ul class="space-y-1.5">
                    <li v-for="s in general" :key="s.label" class="flex items-center justify-between text-sm">
                        {{ s.label }}<span class="flex gap-1"><kbd v-for="k in s.keys" :key="k">{{ k }}</kbd></span>
                    </li>
                </ul>
            </div>
            <div>
                <h3 class="mb-2 text-xs font-medium text-muted uppercase">Navigation</h3>
                <ul class="space-y-1.5">
                    <li v-for="s in goto" :key="s.route" class="flex items-center justify-between text-sm">
                        {{ s.label }}<span class="flex gap-1"><kbd v-for="k in s.shortcut!.split(' ')" :key="k">{{ k.toUpperCase() }}</kbd></span>
                    </li>
                </ul>
            </div>
        </div>
    </Modal>
</template>
