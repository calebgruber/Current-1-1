<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { ChevronsUpDown, X } from 'lucide-vue-next';
import { http } from '@/lib/http';
import { debounce } from '@/composables/useDebounce';

export interface ComboOption {
    value: string | number;
    label: string;
    description?: string;
}

const model = defineModel<string | number | null>();
const props = defineProps<{
    options?: ComboOption[];
    /** URL returning ComboOption[] (or {data: ComboOption[]}); receives ?q= */
    endpoint?: string;
    placeholder?: string;
    initialLabel?: string | null;
    invalid?: boolean;
}>();
const emit = defineEmits<{ select: [option: ComboOption | null] }>();

const query = ref('');
const open = ref(false);
const active = ref(0);
const remote = ref<ComboOption[]>([]);
const selectedLabel = ref<string | null>(props.initialLabel ?? null);
const id = `cb-${Math.random().toString(36).slice(2, 8)}`;

const items = computed(() => {
    if (props.endpoint) return remote.value;
    const q = query.value.toLowerCase();
    return (props.options ?? []).filter((o) => !q || o.label.toLowerCase().includes(q) || o.description?.toLowerCase().includes(q)).slice(0, 50);
});

watch(
    () => [model.value, props.options] as const,
    () => {
        const match = props.options?.find((o) => o.value === model.value);
        if (match) selectedLabel.value = match.label;
        if (model.value === null || model.value === undefined) selectedLabel.value = null;
    },
    { immediate: true },
);

const fetchRemote = debounce(async () => {
    if (!props.endpoint) return;
    const { data } = await http.get(props.endpoint, { params: { q: query.value } });
    remote.value = Array.isArray(data) ? data : data.data;
    active.value = 0;
}, 200);

watch(query, () => {
    active.value = 0;
    fetchRemote();
});

const choose = (o: ComboOption | null) => {
    model.value = o ? o.value : null;
    selectedLabel.value = o?.label ?? null;
    emit('select', o);
    open.value = false;
    query.value = '';
};

const onKey = (e: KeyboardEvent) => {
    if (e.key === 'ArrowDown') {
        e.preventDefault();
        open.value = true;
        active.value = Math.min(active.value + 1, items.value.length - 1);
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        active.value = Math.max(active.value - 1, 0);
    } else if (e.key === 'Enter' && open.value) {
        e.preventDefault();
        if (items.value[active.value]) choose(items.value[active.value]);
    } else if (e.key === 'Escape') {
        open.value = false;
    }
};

const onFocus = () => {
    open.value = true;
    if (props.endpoint && !remote.value.length) fetchRemote();
};
</script>

<template>
    <div class="relative">
        <input
            :id="id"
            type="text"
            role="combobox"
            :aria-expanded="open"
            :aria-controls="`${id}-list`"
            autocomplete="off"
            :value="open ? query : (selectedLabel ?? '')"
            :placeholder="selectedLabel ?? placeholder ?? 'Select…'"
            :class="['st-input pr-14', invalid ? 'border-red-500' : '']"
            @input="query = ($event.target as HTMLInputElement).value"
            @focus="onFocus"
            @blur="open = false"
            @keydown="onKey"
        />
        <div class="absolute inset-y-0 right-2 flex items-center gap-1 text-faint">
            <button v-if="model !== null && model !== undefined" type="button" class="hover:text-ink" aria-label="Clear" @mousedown.prevent="choose(null)"><X class="size-3.5" /></button>
            <ChevronsUpDown class="size-3.5" aria-hidden="true" />
        </div>
        <ul v-if="open && items.length" :id="`${id}-list`" role="listbox" class="absolute z-40 mt-1 max-h-64 w-full overflow-auto rounded-lg border border-line bg-surface p-1 shadow-lg">
            <li
                v-for="(o, i) in items"
                :key="o.value"
                role="option"
                :aria-selected="o.value === model"
                :class="['cursor-pointer rounded-md px-2 py-1.5', i === active ? 'bg-hover' : '']"
                @mousedown.prevent="choose(o)"
                @mouseenter="active = i"
            >
                <div class="text-sm">{{ o.label }}</div>
                <div v-if="o.description" class="text-xs text-muted">{{ o.description }}</div>
            </li>
        </ul>
    </div>
</template>
