<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import CustomFieldInputs from '@/Components/custom-fields/CustomFieldInputs.vue';
import type { CustomFieldSchema, Option } from '@/types';

const props = defineProps<{ product: Record<string, any> | null; categories: Option[]; manufacturers: Option[]; locations: Option[]; trackingOptions: Option[]; customFieldSchema: CustomFieldSchema[]; customFields: Record<string, any> }>();
const p = props.product;
const form = useForm({
    name: p?.name ?? '', sku: p?.sku ?? '', barcode: p?.barcode ?? '', model: p?.model ?? '',
    description: p?.description ?? '', tracking: p?.tracking ?? 'bulk', category_id: p?.category_id ?? null,
    manufacturer_id: p?.manufacturer_id ?? null, default_location_id: p?.default_location_id ?? null,
    replacement_cost: p?.replacement_cost ?? '', rental_price: p?.rental_price ?? '', weight_kg: p?.weight_kg ?? '',
    is_active: p?.is_active ?? true, custom_fields: { ...props.customFields },
});
const submit = () => p ? form.put(`/inventory/products/${p.id}`) : form.post('/inventory/products');
</script>
<template>
    <PageHeader :title="p ? 'Edit product' : 'New product'" />
    <form class="st-card max-w-3xl space-y-4 p-5" @submit.prevent="submit">
        <div v-if="Object.keys(form.errors).length" class="text-sm text-red-600" role="alert"><p v-for="(error, key) in form.errors" :key="key">{{ error }}</p></div>
        <div class="grid gap-4 sm:grid-cols-2">
            <label class="st-label">Name <input v-model="form.name" class="st-input" required></label>
            <label class="st-label">SKU <input v-model="form.sku" class="st-input"></label>
            <label class="st-label">Barcode <input v-model="form.barcode" class="st-input"></label>
            <label class="st-label">Model <input v-model="form.model" class="st-input"></label>
            <label class="st-label">Tracking <select v-model="form.tracking" class="st-input" :disabled="!!p"><option v-for="o in trackingOptions" :key="o.value" :value="o.value">{{ o.label }}</option></select></label>
            <label class="st-label">Category <select v-model="form.category_id" class="st-input"><option :value="null">None</option><option v-for="o in categories" :key="o.value" :value="o.value">{{ o.label }}</option></select></label>
            <label class="st-label">Manufacturer <select v-model="form.manufacturer_id" class="st-input"><option :value="null">None</option><option v-for="o in manufacturers" :key="o.value" :value="o.value">{{ o.label }}</option></select></label>
            <label class="st-label">Default location <select v-model="form.default_location_id" class="st-input"><option :value="null">None</option><option v-for="o in locations" :key="String(o.value)" :value="o.value">{{ o.label }}</option></select></label>
            <label class="st-label">Replacement cost <input v-model="form.replacement_cost" class="st-input" type="number" min="0" step="0.01"></label>
            <label class="st-label">Rental price <input v-model="form.rental_price" class="st-input" type="number" min="0" step="0.01"></label>
            <label class="st-label">Weight (kg) <input v-model="form.weight_kg" class="st-input" type="number" min="0" step="0.001"></label>
        </div>
        <label class="st-label">Description <textarea v-model="form.description" class="st-input" rows="3" /></label>
        <label class="flex items-center gap-2 text-sm"><input v-model="form.is_active" type="checkbox"> Active</label>
        <CustomFieldInputs v-model="form.custom_fields" :schema="customFieldSchema" :errors="form.errors" />
        <Button type="submit" variant="primary" :loading="form.processing">Save product</Button>
    </form>
</template>
