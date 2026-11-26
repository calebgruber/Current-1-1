<script setup lang="ts" generic="T extends Record<string, any>">
import { router } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp } from 'lucide-vue-next';

export interface Column {
    key: string;
    label: string;
    sortable?: boolean;
    align?: 'left' | 'right' | 'center';
    class?: string;
}

const props = defineProps<{ columns: Column[]; rows: T[]; rowKey?: string; sort?: string | null; direction?: 'asc' | 'desc' | null; rowHref?: (row: T) => string | null }>();

const toggleSort = (key: string) => {
    const direction = props.sort === key && props.direction === 'asc' ? 'desc' : 'asc';
    const params = new URLSearchParams(window.location.search);
    params.set('sort', key);
    params.set('direction', direction);
    params.delete('page');
    router.get(`${window.location.pathname}?${params.toString()}`, {}, { preserveState: true, preserveScroll: true, replace: true });
};

const open = (row: T, event: MouseEvent) => {
    const href = props.rowHref?.(row);
    if (!href) return;
    if ((event.target as HTMLElement).closest('a,button,input,select,label')) return;
    if (event.metaKey || event.ctrlKey) {
        window.open(href, '_blank');
        return;
    }
    router.visit(href);
};
const alignClass = (a?: string) => (a === 'right' ? 'text-right' : a === 'center' ? 'text-center' : '');
</script>

<template>
    <div class="overflow-x-auto">
        <table class="st-table">
            <thead>
                <tr>
                    <th v-for="col in columns" :key="col.key" scope="col" :class="[alignClass(col.align), col.class]" :aria-sort="sort === col.key ? (direction === 'desc' ? 'descending' : 'ascending') : undefined">
                        <button v-if="col.sortable" type="button" class="inline-flex items-center gap-1 uppercase hover:text-ink" @click="toggleSort(col.key)">
                            {{ col.label }}
                            <ArrowUp v-if="sort === col.key && direction !== 'desc'" class="size-3" />
                            <ArrowDown v-else-if="sort === col.key" class="size-3" />
                        </button>
                        <template v-else>{{ col.label }}</template>
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(row, i) in rows" :key="rowKey ? row[rowKey] : i" :class="rowHref ? 'cursor-pointer' : ''" @click="open(row, $event)">
                    <td v-for="col in columns" :key="col.key" :class="[alignClass(col.align), col.class]">
                        <slot :name="`cell-${col.key}`" :row="row" :value="row[col.key]">{{ row[col.key] ?? '—' }}</slot>
                    </td>
                </tr>
                <tr v-if="!rows.length">
                    <td :colspan="columns.length" class="!border-0"><slot name="empty"><p class="py-10 text-center text-sm text-muted">No records found.</p></slot></td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
