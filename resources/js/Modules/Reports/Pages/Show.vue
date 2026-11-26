<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
const props = defineProps<{
    report: { id: number; name: string; description: string | null; source_label: string };
    result: { columns: { key: string; label: string }[]; rows: Record<string, unknown>[]; truncated: boolean } | null;
    error: string | null;
    can: { edit: boolean; delete: boolean; export: boolean };
}>();
const remove = () => { if (confirm('Delete this report?')) router.delete(route('reports.destroy', props.report.id)); };
</script>
<template>
    <PageHeader :title="report.name" :description="report.description || report.source_label">
        <template #actions>
            <Button :href="route('reports.index')">Reports</Button>
            <Button v-if="can.edit" :href="route('reports.edit', report.id)">Edit</Button>
            <a v-for="format in (can.export ? ['csv', 'xlsx', 'pdf'] : [])" :key="format" :href="route('reports.export', { report: report.id, format })" class="inline-flex h-8 items-center rounded-md border border-line bg-surface px-3 text-sm font-medium uppercase hover:bg-hover">{{ format }}</a>
            <Button v-if="can.delete" variant="danger" @click="remove">Delete</Button>
        </template>
    </PageHeader>
    <p v-if="error" class="st-card p-4 text-red-600">{{ error }}</p>
    <div v-else-if="result" class="st-card overflow-x-auto p-4"><p v-if="result.truncated" class="mb-2 text-sm text-muted">Results are truncated.</p><table class="w-full text-left text-sm"><thead><tr><th v-for="c in result.columns" :key="c.key" class="p-2">{{ c.label }}</th></tr></thead><tbody><tr v-for="(row, i) in result.rows" :key="i" class="border-t border-line"><td v-for="c in result.columns" :key="c.key" class="p-2">{{ row[c.key] ?? '—' }}</td></tr></tbody></table><p v-if="!result.rows.length" class="py-4">No rows found.</p></div>
</template>
