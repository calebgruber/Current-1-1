<script setup lang="ts">
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import Pagination from '@/Components/ui/Pagination.vue';
import { usePermissions } from '@/composables/usePermissions';
import type { Paginated } from '@/types';
const props = defineProps<{ products: Paginated<Record<string, any>>; filters: { search: string } }>();
const { can } = usePermissions();
const search = ref(props.filters.search);
const columns = [{ key: 'name', label: 'Product' }, { key: 'sku', label: 'SKU' }, { key: 'tracking_label', label: 'Tracking' }, { key: 'owned', label: 'Owned' }];
</script>
<template>
    <PageHeader title="Products"><template #actions><Button v-if="can('inventory.products.create')" href="/inventory/products/create" variant="primary">Add product</Button></template></PageHeader>
    <form class="mb-4 flex gap-2" @submit.prevent="router.get('/inventory/products', { search }, { preserveState: true, replace: true })"><label class="sr-only" for="search">Search products</label><input id="search" v-model="search" class="st-input max-w-sm" type="search" placeholder="Search products"><Button type="submit">Search</Button></form>
    <div class="st-card overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr class="border-b border-line"><th v-for="col in columns" :key="col.key" class="p-3">{{ col.label }}</th></tr></thead><tbody><tr v-for="p in products.data" :key="p.id" class="border-b border-line"><td v-for="(col, i) in columns" :key="col.key" class="p-3"><Link v-if="i === 0" class="text-accent hover:underline" :href="`/inventory/products/${p.id}`">{{ p[col.key] }}</Link><template v-else>{{ p[col.key] ?? '—' }}</template></td></tr><tr v-if="!products.data.length"><td class="p-4 text-muted" :colspan="columns.length">No products found.</td></tr></tbody></table></div>
    <Pagination :meta="products" />
</template>
