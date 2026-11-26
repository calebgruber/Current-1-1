<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import Pagination from '@/Components/ui/Pagination.vue';
import { usePermissions } from '@/composables/usePermissions';
import type { Paginated } from '@/types';

const props = defineProps<{
    products: Paginated<Record<string, any>>;
    filters: { search: string };
    categories: { value: number; label: string }[];
}>();
const { can } = usePermissions();
const search = ref(props.filters.search);
const selected = ref<number[]>([]);
const action = ref('');
const value = ref('');
const processing = ref(false);
const errors = ref<Record<string, string>>({});
const columns = [{ key: 'name', label: 'Product' }, { key: 'sku', label: 'SKU' }, { key: 'tracking_label', label: 'Tracking' }, { key: 'owned', label: 'Owned' }];
const allSelected = computed(() => props.products.data.length > 0 && selected.value.length === props.products.data.length);
const pageKey = computed(() => `${props.products.current_page}:${props.products.data.map(p => p.id).join(',')}`);
watch(pageKey, () => { selected.value = []; errors.value = {}; });
watch(action, () => { value.value = ''; errors.value = {}; });

function selectPage(event: Event) {
    selected.value = (event.target as HTMLInputElement).checked ? props.products.data.map(p => p.id) : [];
}

function submitBulk() {
    if (!selected.value.length || !action.value || !value.value || processing.value) return;
    const description = action.value === 'category_id'
        ? (value.value === 'none' ? 'remove their category' : `set their category to ${props.categories.find(c => String(c.value) === value.value)?.label}`)
        : `mark them ${value.value === '1' ? 'active' : 'inactive'}`;
    if (!window.confirm(`Update ${selected.value.length} selected product(s) and ${description}?`)) return;
    const changes = action.value === 'category_id'
        ? { category_id: value.value === 'none' ? null : Number(value.value) }
        : { is_active: value.value === '1' };
    router.patch('/inventory/products/bulk', { product_ids: selected.value, changes }, {
        preserveScroll: true,
        onStart: () => { processing.value = true; errors.value = {}; },
        onError: (responseErrors) => { errors.value = responseErrors; },
        onSuccess: () => { selected.value = []; action.value = ''; value.value = ''; },
        onFinish: () => { processing.value = false; },
    });
}
</script>
<template>
    <PageHeader title="Products"><template #actions><Button v-if="can('inventory.products.create')" href="/inventory/products/create" variant="primary">Add product</Button></template></PageHeader>
    <form class="mb-4 flex gap-2" @submit.prevent="selected = []; router.get('/inventory/products', { search }, { preserveState: true, replace: true })"><label class="sr-only" for="search">Search products</label><input id="search" v-model="search" class="st-input max-w-sm" type="search" placeholder="Search products"><Button type="submit">Search</Button></form>
    <form v-if="can('inventory.products.edit')" class="st-card mb-4 flex flex-wrap items-end gap-3 p-4" @submit.prevent="submitBulk">
        <p class="w-full text-sm" aria-live="polite">{{ selected.length }} selected on this page. Selection is cleared when changing pages or searching.</p>
        <div><label class="mb-1 block text-sm" for="bulk-action">Bulk action</label><select id="bulk-action" v-model="action" class="st-input"><option value="">Choose an action</option><option value="category_id">Set category</option><option value="is_active">Set active state</option></select></div>
        <div v-if="action === 'category_id'"><label class="mb-1 block text-sm" for="bulk-category">Category</label><select id="bulk-category" v-model="value" class="st-input"><option value="">Choose a category</option><option value="none">Remove category</option><option v-for="category in categories" :key="category.value" :value="String(category.value)">{{ category.label }}</option></select></div>
        <div v-if="action === 'is_active'"><label class="mb-1 block text-sm" for="bulk-active">Active state</label><select id="bulk-active" v-model="value" class="st-input"><option value="">Choose a state</option><option value="1">Active</option><option value="0">Inactive</option></select></div>
        <Button type="submit" variant="primary" :disabled="!selected.length || !action || !value" :loading="processing">Apply to selected</Button>
        <div v-if="Object.keys(errors).length" class="w-full text-sm text-red-600" role="alert"><p v-for="(error, key) in errors" :key="key">{{ error }}</p></div>
    </form>
    <div class="st-card overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr class="border-b border-line"><th v-if="can('inventory.products.edit')" class="p-3"><input type="checkbox" aria-label="Select all products on this page" :checked="allSelected" :indeterminate.prop="selected.length > 0 && !allSelected" :disabled="!products.data.length || processing" @change="selectPage"></th><th v-for="col in columns" :key="col.key" class="p-3" scope="col">{{ col.label }}</th></tr></thead><tbody><tr v-for="p in products.data" :key="p.id" class="border-b border-line"><td v-if="can('inventory.products.edit')" class="p-3"><input v-model="selected" type="checkbox" :value="p.id" :aria-label="`Select ${p.name}`" :disabled="processing"></td><td v-for="(col, i) in columns" :key="col.key" class="p-3"><Link v-if="i === 0" class="st-link" :href="`/inventory/products/${p.id}`">{{ p[col.key] }}</Link><template v-else>{{ p[col.key] ?? '—' }}</template></td></tr><tr v-if="!products.data.length"><td class="p-4 text-muted" :colspan="columns.length + (can('inventory.products.edit') ? 1 : 0)">No products found.</td></tr></tbody></table></div>
    <Pagination :meta="products" />
</template>
