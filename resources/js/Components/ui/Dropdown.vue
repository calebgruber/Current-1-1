<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';

withDefaults(defineProps<{ align?: 'left' | 'right'; width?: string }>(), { align: 'right', width: 'w-56' });
const open = ref(false);
const root = ref<HTMLElement>();

const onDocClick = (e: MouseEvent) => {
    if (root.value && !root.value.contains(e.target as Node)) open.value = false;
};
const onKey = (e: KeyboardEvent) => {
    if (e.key === 'Escape') open.value = false;
};
onMounted(() => {
    document.addEventListener('mousedown', onDocClick);
    document.addEventListener('keydown', onKey);
});
onBeforeUnmount(() => {
    document.removeEventListener('mousedown', onDocClick);
    document.removeEventListener('keydown', onKey);
});
</script>

<template>
    <div ref="root" class="relative inline-block">
        <div @click="open = !open"><slot name="trigger" :open="open" /></div>
        <Transition enter-from-class="opacity-0 scale-95" leave-to-class="opacity-0 scale-95" enter-active-class="transition duration-100" leave-active-class="transition duration-75">
            <div v-if="open" role="menu" :class="['absolute z-40 mt-1 origin-top rounded-lg border border-line bg-surface p-1 shadow-lg', width, align === 'right' ? 'right-0' : 'left-0']" @click="open = false">
                <slot />
            </div>
        </Transition>
    </div>
</template>
