<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import CustomFieldDisplay from '@/Components/custom-fields/CustomFieldDisplay.vue';
import { usePermissions } from '@/composables/usePermissions';
import type { CustomFieldSchema, Option } from '@/types';
const props = defineProps<{ product: Record<string, any>; availability: Record<string, any>; assets: Record<string, any>[]; stock: Record<string, any>[]; maintenance: Record<string, any>[]; locations: Option[]; customFieldSchema: CustomFieldSchema[]; customFields: Record<string, any> }>();
const { can } = usePermissions();
const stockForm = useForm({ location_id: null as number | null, quantity: 0, quantity_maintenance: 0, quantity_damaged: 0, quantity_lost: 0, reason: '' });
const editStock = (row: Record<string, any>) => { stockForm.location_id = row.location_id; stockForm.quantity = row.quantity; stockForm.quantity_maintenance = row.quantity_maintenance; stockForm.quantity_damaged = row.quantity_damaged; stockForm.quantity_lost = row.quantity_lost; };
</script>
<template>
    <PageHeader :title="product.name" :description="product.sku || product.tracking_label">
        <template #actions><Button v-if="can('inventory.products.edit')" :href="`/inventory/products/${product.id}/edit`">Edit</Button><Button v-if="can('inventory.availability.view')" :href="`/availability?product=${product.id}`">Availability</Button><Button v-if="product.tracking === 'serialized' && can('assets.assets.create')" :href="`/assets/create?product=${product.id}`">Add asset</Button></template>
    </PageHeader>
    <div class="space-y-4">
        <Card title="Details"><p>{{ product.description || 'No description.' }}</p><p class="mt-2 text-sm text-muted">Model: {{ product.model || '—' }} · Barcode: {{ product.barcode || '—' }} · {{ product.is_active ? 'Active' : 'Inactive' }}</p><CustomFieldDisplay :schema="customFieldSchema" :values="customFields" /></Card>
        <Card title="Availability (next 30 days)"><p>Owned: {{ availability.owned }} · Available: {{ availability.available }} · Reserved: {{ availability.reserved }} · In maintenance: {{ availability.maintenance }}</p></Card>
        <Card v-if="product.tracking === 'serialized'" title="Assets">
            <ul class="space-y-2"><li v-for="a in assets" :key="a.id"><Link v-if="can('assets.assets.view')" :href="`/assets/${a.id}`" class="st-link">{{ a.asset_number }}</Link><span v-else>{{ a.asset_number }}</span> — {{ a.status_label }} · {{ a.location || 'Unassigned' }}</li><li v-if="!assets.length" class="text-muted">No assets.</li></ul>
        </Card>
        <Card v-else title="Stock by location">
            <ul class="space-y-2"><li v-for="row in stock" :key="row.id">{{ row.location }}: {{ row.quantity }} (maintenance {{ row.quantity_maintenance }}, damaged {{ row.quantity_damaged }}, lost {{ row.quantity_lost }}) <button v-if="can('inventory.stock.edit')" type="button" class="ml-2 text-accent underline" @click="editStock(row)">Adjust</button></li><li v-if="!stock.length" class="text-muted">No stock recorded.</li></ul>
            <form v-if="can('inventory.stock.edit')" class="mt-4 grid gap-3 sm:grid-cols-2" @submit.prevent="stockForm.put(`/inventory/products/${product.id}/stock`, { preserveScroll: true })">
                <div v-if="Object.keys(stockForm.errors).length" class="text-red-600 sm:col-span-2" role="alert"><p v-for="(error, key) in stockForm.errors" :key="key">{{ error }}</p></div>
                <label class="st-label">Location <select v-model="stockForm.location_id" class="st-input"><option :value="null">Unassigned</option><option v-for="o in locations" :key="String(o.value)" :value="o.value">{{ o.label }}</option></select></label>
                <label class="st-label">Quantity <input v-model.number="stockForm.quantity" class="st-input" type="number" min="0" required></label>
                <label class="st-label">In maintenance <input v-model.number="stockForm.quantity_maintenance" class="st-input" type="number" min="0"></label>
                <label class="st-label">Damaged <input v-model.number="stockForm.quantity_damaged" class="st-input" type="number" min="0"></label>
                <label class="st-label">Lost <input v-model.number="stockForm.quantity_lost" class="st-input" type="number" min="0"></label>
                <label class="st-label">Reason <input v-model="stockForm.reason" class="st-input"></label>
                <div><Button type="submit" variant="primary" :loading="stockForm.processing">Save stock</Button></div>
            </form>
        </Card>
        <Card title="Maintenance"><ul class="space-y-2"><li v-for="m in maintenance" :key="m.id">{{ m.reference }} — {{ m.title }} ({{ m.status_label }})</li><li v-if="!maintenance.length" class="text-muted">No maintenance records.</li></ul></Card>
    </div>
</template>
