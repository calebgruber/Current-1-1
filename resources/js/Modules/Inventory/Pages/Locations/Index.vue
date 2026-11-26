<script setup lang="ts">
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import CustomFieldDisplay from '@/Components/custom-fields/CustomFieldDisplay.vue';
import { usePermissions } from '@/composables/usePermissions';
import type { CustomFieldSchema, Option } from '@/types';

interface LocationRow {
    id: number; path: string; code: string | null; barcode: string | null; type: string;
    is_active: boolean; children_count: number; assets_count: number; bulk_units: number;
    products_count: number; custom_fields: Record<string, any>;
}

const props = defineProps<{ locations: LocationRow[]; filters: { search: string; type: string | null }; types: Option[]; customFieldColumns: CustomFieldSchema[] }>();
const { can } = usePermissions();
const search = ref(props.filters.search);
const type = ref(props.filters.type);
</script>

<template>
    <PageHeader title="Locations" description="Manage the locations where equipment is stored.">
        <template #actions><Button v-if="can('inventory.locations.create')" :href="route('inventory.locations.create')" variant="primary">Add location</Button></template>
    </PageHeader>
    <form class="mb-4 flex flex-wrap gap-2" @submit.prevent="router.get(route('inventory.locations.index'), { search, type }, { preserveState: true, replace: true })">
        <label class="sr-only" for="location-search">Search locations</label>
        <input id="location-search" v-model="search" class="st-input max-w-sm" type="search" placeholder="Search locations">
        <label class="sr-only" for="location-type">Location type</label>
        <select id="location-type" v-model="type" class="st-input max-w-xs">
            <option :value="null">All types</option>
            <option v-for="option in types" :key="String(option.value)" :value="option.value">{{ option.label }}</option>
        </select>
        <Button type="submit">Search</Button>
    </form>
    <div class="st-card overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead><tr class="border-b border-line"><th class="p-3">Location</th><th class="p-3">Type</th><th class="p-3">Code</th><th class="p-3">Barcode</th><th class="p-3">Status</th><th class="p-3">Sub-locations</th><th class="p-3">Assets</th><th class="p-3">Bulk units</th><th class="p-3">Products</th><th v-for="field in customFieldColumns" :key="field.key" class="p-3">{{ field.label }}</th></tr></thead>
            <tbody>
                <tr v-for="location in locations" :key="location.id" class="border-b border-line">
                    <td class="p-3"><Link :href="route('inventory.locations.show', location.id)" class="text-accent hover:underline">{{ location.path }}</Link></td>
                    <td class="p-3 capitalize">{{ location.type }}</td><td class="p-3">{{ location.code || '—' }}</td><td class="p-3">{{ location.barcode || '—' }}</td>
                    <td class="p-3">{{ location.is_active ? 'Active' : 'Inactive' }}</td><td class="p-3">{{ location.children_count }}</td><td class="p-3">{{ location.assets_count }}</td><td class="p-3">{{ location.bulk_units }}</td><td class="p-3">{{ location.products_count }}</td>
                    <td v-for="field in customFieldColumns" :key="field.key" class="p-3"><CustomFieldDisplay :schema="[field]" :values="location.custom_fields" /></td>
                </tr>
                <tr v-if="!locations.length"><td :colspan="9 + customFieldColumns.length" class="p-4 text-muted">No locations found.</td></tr>
            </tbody>
        </table>
    </div>
</template>
