<script setup lang="ts">
import { nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { X } from 'lucide-vue-next';

const open = defineModel<boolean>('open', { default: false });
const props = withDefaults(defineProps<{ title?: string; description?: string; size?: 'sm' | 'md' | 'lg' | 'xl' | '2xl' }>(), { size: 'md' });
const emit = defineEmits<{ close: [] }>();

const panel = ref<HTMLElement>();
let previous: HTMLElement | null = null;

const close = () => {
    open.value = false;
    emit('close');
};

const onKey = (e: KeyboardEvent) => {
    if (e.key === 'Escape') {
        e.stopPropagation();
        close();
    }
    if (e.key === 'Tab' && panel.value) {
        const focusable = panel.value.querySelectorAll<HTMLElement>('a[href],button:not([disabled]),input:not([disabled]),select,textarea,[tabindex]:not([tabindex="-1"])');
        if (!focusable.length) return;
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (e.shiftKey && document.activeElement === first) {
            e.preventDefault();
            last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
            e.preventDefault();
            first.focus();
        }
    }
};

watch(open, async (value) => {
    if (value) {
        previous = document.activeElement as HTMLElement;
        document.addEventListener('keydown', onKey, true);
        await nextTick();
        (panel.value?.querySelector<HTMLElement>('[autofocus],input,select,textarea,button') ?? panel.value)?.focus();
    } else {
        document.removeEventListener('keydown', onKey, true);
        previous?.focus();
    }
}, { immediate: true });

onBeforeUnmount(() => document.removeEventListener('keydown', onKey, true));

const widths = { sm: 'max-w-sm', md: 'max-w-lg', lg: 'max-w-2xl', xl: 'max-w-4xl', '2xl': 'max-w-6xl' };
void props;
</script>

<template>
    <Teleport to="body">
        <Transition enter-from-class="opacity-0" leave-to-class="opacity-0" enter-active-class="transition duration-150" leave-active-class="transition duration-100">
            <div v-if="open" class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/40 p-4 pt-[10vh] backdrop-blur-[2px]" @mousedown.self="close">
                <div ref="panel" role="dialog" aria-modal="true" :aria-label="title" tabindex="-1" :class="['w-full rounded-xl border border-line bg-surface shadow-2xl outline-none', widths[size]]">
                    <header v-if="title" class="flex items-start justify-between gap-4 border-b border-line px-5 py-4">
                        <div>
                            <h2 class="text-base font-semibold">{{ title }}</h2>
                            <p v-if="description" class="mt-0.5 text-sm text-muted">{{ description }}</p>
                        </div>
                        <button type="button" class="rounded p-1 text-muted hover:bg-hover hover:text-ink" aria-label="Close" @click="close"><X class="size-4" /></button>
                    </header>
                    <div class="px-5 py-4"><slot /></div>
                    <footer v-if="$slots.footer" class="flex justify-end gap-2 border-t border-line px-5 py-3"><slot name="footer" /></footer>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
