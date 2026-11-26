<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import SearchInput from '@/Components/ui/SearchInput.vue';
import Card from '@/Components/ui/Card.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import Badge from '@/Components/ui/Badge.vue';
import { debounce } from '@/composables/useDebounce';
import type { Option } from '@/types';

const props = defineProps<{
    q: string;
    type: string | null;
    results: { type: string; type_label: string; id: number; title: string; subtitle: string | null; url: string; exact: boolean }[];
    types: Option<string>[];
}>();

const term = ref(props.q);
const reload = debounce((q: string, type: string | null) => router.get(route('search.index'), { q, type: type ?? undefined }, { preserveState: true, replace: true }), 250);
watch(term, (q) => reload(q, props.type));

const groups = computed(() => {
    const map = new Map<string, typeof props.results>();
    for (const r of props.results) map.set(r.type_label, [...(map.get(r.type_label) ?? []), r]);
    return [...map.entries()];
});
</script>

<template>
    <PageHeader title="Search" description="Inventory, serial numbers, barcodes, jobs, contacts, maintenance, documents, reports and locations." />
    <div class="mb-4 flex flex-wrap items-center gap-3">
        <SearchInput v-model="term" class="w-full max-w-xl" placeholder="Search or scan a barcode…" autofocus />
        <div class="flex flex-wrap gap-1">
            <Link :href="route('search.index', { q })" :class="['rounded-full px-3 py-1 text-xs', !type ? 'bg-accent text-white dark:text-zinc-950' : 'bg-subtle text-muted hover:text-ink']">All</Link>
            <Link v-for="t in types" :key="t.value" :href="route('search.index', { q, type: t.value })" :class="['rounded-full px-3 py-1 text-xs', type === t.value ? 'bg-accent text-white dark:text-zinc-950' : 'bg-subtle text-muted hover:text-ink']">{{ t.label }}</Link>
        </div>
    </div>
    <Card v-if="!q" :padded="false"><EmptyState title="Start typing to search" description="Tip: press ⌘K / Ctrl K from anywhere." /></Card>
    <Card v-else-if="!results.length" :padded="false"><EmptyState :title="`No results for “${q}”`" /></Card>
    <div v-else class="space-y-4">
        <Card v-for="[label, items] in groups" :key="label" :title="label" :padded="false">
            <ul class="divide-y divide-line">
                <li v-for="r in items" :key="`${r.type}-${r.id}`">
                    <Link :href="r.url" class="m-2 flex items-center gap-3 rounded-md border border-line bg-surface px-4 py-2.5 transition hover:border-line-strong hover:bg-hover">
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-medium">{{ r.title }}</span>
                            <span v-if="r.subtitle" class="block truncate text-xs text-muted">{{ r.subtitle }}</span>
                        </span>
                        <Badge v-if="r.exact" tone="success">Exact match</Badge>
                    </Link>
                </li>
            </ul>
        </Card>
    </div>
</template>
