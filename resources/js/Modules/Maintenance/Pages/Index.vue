<script setup lang="ts">
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import Button from '@/Components/ui/Button.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Pagination from '@/Components/ui/Pagination.vue';
import { usePermissions } from '@/composables/usePermissions';
import type { Paginated } from '@/types';

const props = defineProps<{ records: Paginated<Record<string, any>>; filters: { search?: string; status?: string }; statuses: { value: string; label: string }[] }>();
const { can } = usePermissions();
const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? '');
</script>

<template>
    <PageHeader title="Maintenance"><template #actions><Button v-if="can('maintenance.records.create')" href="/maintenance/create" variant="primary">New record</Button></template></PageHeader>
    <form class="mb-4 flex flex-wrap gap-2" @submit.prevent="router.get('/maintenance', { search, status }, { preserveState: true, replace: true })">
        <label class="sr-only" for="maintenance-search">Search maintenance</label><input id="maintenance-search" v-model="search" class="st-input max-w-sm" type="search" placeholder="Search reference or title">
        <label class="sr-only" for="maintenance-status">Status</label><select id="maintenance-status" v-model="status" class="st-input max-w-xs"><option value="">All statuses</option><option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option></select>
        <Button type="submit">Search</Button>
    </form>
    <div class="st-card overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr class="border-b border-line"><th class="p-3">Reference</th><th class="p-3">Title</th><th class="p-3">Product / asset</th><th class="p-3">Status</th><th class="p-3">Assigned to</th></tr></thead><tbody>
        <tr v-for="r in records.data" :key="r.id" class="border-b border-line"><td class="p-3"><Link class="st-link" :href="`/maintenance/${r.id}`">{{ r.reference }}</Link></td><td class="p-3">{{ r.title }}</td><td class="p-3">{{ r.product?.name }}{{ r.asset ? ` · ${r.asset.asset_number}` : '' }}</td><td class="p-3">{{ statuses.find(s => s.value === r.status)?.label ?? r.status }}</td><td class="p-3">{{ r.assignee?.name ?? '—' }}</td></tr>
        <tr v-if="!records.data.length"><td colspan="5" class="p-4 text-muted">No maintenance records found.</td></tr>
    </tbody></table></div><Pagination :meta="records" />
</template>
