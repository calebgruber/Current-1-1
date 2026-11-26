<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import Pagination from '@/Components/ui/Pagination.vue';
import Badge from '@/Components/ui/Badge.vue';
import { assetStatusTone } from '@/lib/status';
import { usePermissions } from '@/composables/usePermissions';
import type { Paginated } from '@/types';
const props = defineProps<{ assets: Paginated<Record<string, any>>; filters: { search: string } }>();
const { can } = usePermissions();
const search = ref(props.filters.search);
const records = computed<Paginated<Record<string, any>>>(() => ({ ...props.assets, data: props.assets.data.map(a => ({ ...a, product_name: a.product?.name, location_name: a.location?.name })) }));
const columns = [{ key: 'asset_number', label: 'Asset' }, { key: 'product_name', label: 'Product' }, { key: 'serial_number', label: 'Serial number' }, { key: 'status_label', label: 'Status' }, { key: 'location_name', label: 'Location' }];
</script>
<template>
    <PageHeader title="Assets"><template #actions><Button v-if="can('assets.assets.create')" href="/assets/create" variant="primary">Add asset</Button></template></PageHeader>
    <form class="mb-4 flex gap-2" @submit.prevent="router.get('/assets', { search }, { preserveState: true, replace: true })"><label class="sr-only" for="search">Search assets</label><input id="search" v-model="search" class="st-input max-w-sm" type="search" placeholder="Search assets"><Button type="submit">Search</Button></form>
    <div class="st-card overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr class="border-b border-line"><th v-for="col in columns" :key="col.key" class="p-3">{{ col.label }}</th></tr></thead><tbody><tr v-for="a in records.data" :key="a.id" class="border-b border-line"><td v-for="(col, i) in columns" :key="col.key" class="p-3"><Link v-if="i === 0" class="st-link" :href="`/assets/${a.id}`">{{ a[col.key] }}</Link><Badge v-else-if="col.key === 'status_label'" :tone="assetStatusTone[a.status]">{{ a[col.key] ?? '—' }}</Badge><template v-else>{{ a[col.key] ?? '—' }}</template></td></tr><tr v-if="!records.data.length"><td class="p-4 text-muted" :colspan="columns.length">No assets found.</td></tr></tbody></table></div>
    <Pagination :meta="records" />
</template>
