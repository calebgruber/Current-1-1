<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { Briefcase, Calendar, ChartBar, CornerDownLeft, FileText, MapPin, Package, Search, Tag, User, Wrench, type LucideIcon } from 'lucide-vue-next';
import { http } from '@/lib/http';
import { debounce } from '@/composables/useDebounce';
import { navigation } from '@/lib/navigation';
import { usePermissions } from '@/composables/usePermissions';

const open = defineModel<boolean>('open', { default: false });

interface Result {
    type: string;
    type_label: string;
    id: number;
    title: string;
    subtitle: string | null;
    url: string;
    icon: string;
}
interface Entry {
    key: string;
    title: string;
    subtitle?: string | null;
    group: string;
    icon: LucideIcon | unknown;
    url: string;
}

const icons: Record<string, LucideIcon> = { cube: Package, tag: Tag, briefcase: Briefcase, calendar: Calendar, user: User, wrench: Wrench, document: FileText, chart: ChartBar, 'map-pin': MapPin };

const query = ref('');
const results = ref<Result[]>([]);
const loading = ref(false);
const active = ref(0);
const input = ref<HTMLInputElement>();
const { can } = usePermissions();

const pages = computed<Entry[]>(() =>
    navigation
        .flatMap((s) => s.items)
        .filter((i) => route().has(i.route) && (!i.permissions || i.permissions.some(can)))
        .map((i) => ({ key: `nav:${i.route}`, title: i.label, group: 'Go to', icon: i.icon, url: route(i.route), subtitle: i.shortcut ? i.shortcut.toUpperCase() : null })),
);

const entries = computed<Entry[]>(() => {
    const q = query.value.trim().toLowerCase();
    const nav = pages.value.filter((p) => !q || p.title.toLowerCase().includes(q)).slice(0, q ? 4 : 20);
    const found = results.value.map((r) => ({ key: `${r.type}:${r.id}`, title: r.title, subtitle: r.subtitle, group: r.type_label, icon: icons[r.icon] ?? Search, url: r.url }));
    return [...found, ...nav];
});

const grouped = computed(() => {
    const groups: { label: string; items: (Entry & { index: number })[] }[] = [];
    entries.value.forEach((e, index) => {
        let g = groups.find((x) => x.label === e.group);
        if (!g) groups.push((g = { label: e.group, items: [] }));
        g.items.push({ ...e, index });
    });
    return groups;
});

const fetch = debounce(async (q: string) => {
    if (!q.trim()) {
        results.value = [];
        loading.value = false;
        return;
    }
    try {
        const { data } = await http.get(route('search.query'), { params: { q, limit: 20 } });
        if (q === query.value) results.value = data.results;
    } finally {
        loading.value = false;
    }
}, 120);

watch(query, (q) => {
    active.value = 0;
    loading.value = true;
    fetch(q);
});

watch(open, async (value) => {
    if (value) {
        query.value = '';
        results.value = [];
        await nextTick();
        input.value?.focus();
    }
});

const go = (entry?: Entry) => {
    if (!entry) return;
    open.value = false;
    router.visit(entry.url);
};

const onKey = (e: KeyboardEvent) => {
    if (e.key === 'ArrowDown') {
        e.preventDefault();
        active.value = (active.value + 1) % Math.max(entries.value.length, 1);
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        active.value = (active.value - 1 + entries.value.length) % Math.max(entries.value.length, 1);
    } else if (e.key === 'Enter') {
        e.preventDefault();
        if (entries.value[active.value]) go(entries.value[active.value]);
        else if (query.value.trim()) {
            open.value = false;
            router.visit(route('search.index', { q: query.value }));
        }
    } else if (e.key === 'Escape') {
        open.value = false;
    }
};

watch(active, async () => {
    await nextTick();
    document.querySelector('[data-palette-active="true"]')?.scrollIntoView({ block: 'nearest' });
});
</script>

<template>
    <Teleport to="body">
        <Transition enter-from-class="opacity-0" leave-to-class="opacity-0" enter-active-class="transition duration-100" leave-active-class="transition duration-75">
            <div v-if="open" class="fixed inset-0 z-[70] flex items-start justify-center bg-black/40 p-4 pt-[12vh] backdrop-blur-[2px]" @mousedown.self="open = false">
                <div role="dialog" aria-modal="true" aria-label="Search" class="w-full max-w-2xl overflow-hidden rounded-xl border border-line bg-surface shadow-2xl">
                    <div class="flex items-center gap-3 border-b border-line px-4">
                        <Search class="size-4 text-faint" aria-hidden="true" />
                        <input
                            ref="input"
                            v-model="query"
                            type="text"
                            role="combobox"
                            aria-expanded="true"
                            aria-controls="palette-list"
                            :aria-activedescendant="entries[active] ? `palette-${active}` : undefined"
                            placeholder="Search equipment, serials, barcodes, jobs, contacts…"
                            class="h-14 flex-1 bg-transparent text-base outline-none placeholder:text-faint"
                            @keydown="onKey"
                        />
                        <kbd>esc</kbd>
                    </div>
                    <div id="palette-list" role="listbox" class="max-h-[60vh] overflow-y-auto p-2">
                        <p v-if="query && !loading && !entries.length" class="px-3 py-8 text-center text-sm text-muted">No results for “{{ query }}”. Press Enter for full search.</p>
                        <div v-for="group in grouped" :key="group.label" class="mb-1">
                            <div class="px-3 pt-2 pb-1 text-[11px] font-medium tracking-wide text-faint uppercase">{{ group.label }}</div>
                            <button
                                v-for="item in group.items"
                                :id="`palette-${item.index}`"
                                :key="item.key"
                                type="button"
                                role="option"
                                :aria-selected="item.index === active"
                                :data-palette-active="item.index === active"
                                :class="['flex w-full items-center gap-3 rounded-lg px-3 py-2 text-left', item.index === active ? 'bg-hover' : '']"
                                @mouseenter="active = item.index"
                                @click="go(item)"
                            >
                                <component :is="item.icon" class="size-4 shrink-0 text-muted" aria-hidden="true" />
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm">{{ item.title }}</span>
                                    <span v-if="item.subtitle" class="block truncate text-xs text-muted">{{ item.subtitle }}</span>
                                </span>
                                <CornerDownLeft v-if="item.index === active" class="size-3.5 text-faint" aria-hidden="true" />
                            </button>
                        </div>
                    </div>
                    <div class="flex items-center gap-4 border-t border-line px-4 py-2 text-xs text-faint">
                        <span><kbd>↑</kbd> <kbd>↓</kbd> navigate</span>
                        <span><kbd>↵</kbd> open</span>
                        <span class="ml-auto">Scan a barcode to jump straight to it</span>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
