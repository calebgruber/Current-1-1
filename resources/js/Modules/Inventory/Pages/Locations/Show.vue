<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import CustomFieldDisplay from '@/Components/custom-fields/CustomFieldDisplay.vue';
import { usePermissions } from '@/composables/usePermissions';
import type { CustomFieldSchema } from '@/types';

interface Location {
    id: number; name: string; path: string; code: string | null; type: string;
    barcode: string | null; address: string | null; notes: string | null; is_active: boolean;
}
interface Child { id: number; name: string; code: string | null; type: string; is_active: boolean; children_count: number; assets_count: number }
interface Asset { id: number; asset_number: string; serial_number: string | null; product: { id: number; name: string } | null; status_label: string }
interface Stock { product: { id: number; name: string; sku: string | null }; quantity: number; quantity_maintenance: number; quantity_damaged: number; quantity_lost: number }
interface History { id: number; event: string; description: string | null; user: string | null; created_at: string | null; changes: { field: string; from: unknown; to: unknown }[] }

const props = defineProps<{ location: Location; breadcrumbs: { id: number; name: string }[]; children: Child[]; assets: Asset[]; assetsTotal: number; stock: Stock[]; label: { code: string; title: string; subtitle: string | null; barcode_svg: string | null; qr_svg: string | null }; history: History[]; customFieldSchema: CustomFieldSchema[]; customFields: Record<string, any> }>();
const { can } = usePermissions();
function remove() {
    if (window.confirm(`Delete "${props.location.name}"?`)) router.delete(route('inventory.locations.destroy', props.location.id));
}
</script>

<template>
    <PageHeader :title="location.name" :description="location.path">
        <template #actions>
            <Button v-if="can('inventory.locations.create')" :href="route('inventory.locations.create', { parent: location.id })">Add sub-location</Button>
            <Button v-if="can('inventory.locations.edit')" :href="route('inventory.locations.edit', location.id)">Edit</Button>
            <Button v-if="can('inventory.locations.delete')" @click="remove">Delete</Button>
        </template>
    </PageHeader>
    <nav aria-label="Location hierarchy" class="mb-4 text-sm">
        <Link :href="route('inventory.locations.index')" class="text-accent hover:underline">Locations</Link>
        <template v-for="ancestor in breadcrumbs" :key="ancestor.id"> › <Link :href="route('inventory.locations.show', ancestor.id)" class="text-accent hover:underline">{{ ancestor.name }}</Link></template>
        › {{ location.name }}
    </nav>
    <div class="space-y-4">
        <Card title="Details">
            <p class="capitalize">{{ location.type }} · {{ location.is_active ? 'Active' : 'Inactive' }}</p>
            <p class="mt-2 text-sm text-muted">Code: {{ location.code || '—' }} · Barcode: {{ location.barcode || '—' }}</p>
            <p v-if="location.address" class="mt-2 whitespace-pre-line">{{ location.address }}</p>
            <p v-if="location.notes" class="mt-2 whitespace-pre-line">{{ location.notes }}</p>
            <div class="mt-3"><CustomFieldDisplay :schema="customFieldSchema" :values="customFields" /></div>
        </Card>
        <Card title="Sub-locations">
            <ul class="space-y-2"><li v-for="child in children" :key="child.id"><Link :href="route('inventory.locations.show', child.id)" class="text-accent hover:underline">{{ child.name }}</Link> — {{ child.type }}{{ child.code ? ` · ${child.code}` : '' }} · {{ child.is_active ? 'Active' : 'Inactive' }} · {{ child.children_count }} sub-locations · {{ child.assets_count }} assets</li><li v-if="!children.length" class="text-muted">No sub-locations.</li></ul>
        </Card>
        <Card title="Assets">
            <p v-if="assetsTotal > assets.length" class="mb-2 text-sm text-muted">Showing {{ assets.length }} of {{ assetsTotal }} assets.</p>
            <ul class="space-y-2"><li v-for="asset in assets" :key="asset.id"><Link v-if="can('assets.assets.view')" :href="route('assets.show', asset.id)" class="text-accent hover:underline">{{ asset.asset_number }}</Link><span v-else>{{ asset.asset_number }}</span> — {{ asset.product?.name || 'Unknown product' }} · {{ asset.status_label }}<span v-if="asset.serial_number"> · Serial: {{ asset.serial_number }}</span></li><li v-if="!assets.length" class="text-muted">No assets at this location.</li></ul>
        </Card>
        <Card title="Bulk stock">
            <ul class="space-y-2"><li v-for="row in stock" :key="row.product.id"><Link v-if="can('inventory.products.view')" :href="route('inventory.products.show', row.product.id)" class="text-accent hover:underline">{{ row.product.name }}</Link><span v-else>{{ row.product.name }}</span><span v-if="row.product.sku"> ({{ row.product.sku }})</span>: {{ row.quantity }} · Maintenance {{ row.quantity_maintenance }} · Damaged {{ row.quantity_damaged }} · Lost {{ row.quantity_lost }}</li><li v-if="!stock.length" class="text-muted">No bulk stock at this location.</li></ul>
        </Card>
        <Card title="Label">
            <p>{{ label.title }} · {{ label.subtitle }} · {{ label.code }}</p>
            <div class="mt-3 flex flex-wrap items-center gap-4">
                <div v-if="label.barcode_svg" aria-label="Barcode" v-html="label.barcode_svg" />
                <div v-if="label.qr_svg" aria-label="QR code" v-html="label.qr_svg" />
            </div>
        </Card>
        <Card title="History">
            <ul class="space-y-3"><li v-for="entry in history" :key="entry.id"><p>{{ entry.created_at }} · {{ entry.user || 'System' }} · {{ entry.description || entry.event }}</p><ul v-if="entry.changes.length" class="ml-4 text-sm text-muted"><li v-for="change in entry.changes" :key="change.field">{{ change.field }}: {{ change.from ?? '—' }} → {{ change.to ?? '—' }}</li></ul></li><li v-if="!history.length" class="text-muted">No history.</li></ul>
        </Card>
    </div>
</template>
