<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import Combobox from '@/Components/ui/Combobox.vue';
import CustomFieldInputs from '@/Components/custom-fields/CustomFieldInputs.vue';
import type { CustomFieldSchema, Option } from '@/types';
const props = defineProps<{ asset: Record<string, any> | null; defaultProduct: { value: number; label: string } | null; conditions: Option[]; locations: Option[]; customFieldSchema: CustomFieldSchema[]; customFields: Record<string, any> }>();
const a = props.asset;
const form = useForm({ product_id: a?.product_id ?? props.defaultProduct?.value ?? null, asset_number: a?.asset_number ?? '', serial_number: a?.serial_number ?? '', barcode: a?.barcode ?? '', condition: a?.condition ?? 'good', location_id: a?.location_id ?? null, purchased_at: a?.purchased_at ?? '', purchase_cost: a?.purchase_cost ?? '', warranty_expires_at: a?.warranty_expires_at ?? '', notes: a?.notes ?? '', custom_fields: { ...props.customFields } });
const submit = () => a ? form.put(`/assets/${a.id}`) : form.post('/assets');
</script>
<template>
    <PageHeader :title="a ? 'Edit asset' : 'New asset'" />
    <form class="st-card max-w-3xl space-y-4 p-5" @submit.prevent="submit">
        <div v-if="Object.keys(form.errors).length" class="text-sm text-red-600" role="alert"><p v-for="(error, key) in form.errors" :key="key">{{ error }}</p></div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div><label class="st-label">Serialized product</label><Combobox v-model="form.product_id" endpoint="/inventory/products/lookup?tracking=serialized" :initial-label="a?.product_name ?? defaultProduct?.label" placeholder="Find a product" /></div>
            <label class="st-label">Asset number <input v-model="form.asset_number" class="st-input" :required="!!a" placeholder="Generated if blank"></label>
            <label class="st-label">Serial number <input v-model="form.serial_number" class="st-input"></label>
            <label class="st-label">Barcode <input v-model="form.barcode" class="st-input"></label>
            <label class="st-label">Condition <select v-model="form.condition" class="st-input"><option v-for="o in conditions" :key="o.value" :value="o.value">{{ o.label }}</option></select></label>
            <label class="st-label">Location <select v-model="form.location_id" class="st-input"><option :value="null">Unassigned</option><option v-for="o in locations" :key="String(o.value)" :value="o.value">{{ o.label }}</option></select></label>
            <label class="st-label">Purchase date <input v-model="form.purchased_at" class="st-input" type="date"></label>
            <label class="st-label">Purchase cost <input v-model="form.purchase_cost" class="st-input" type="number" min="0" step="0.01"></label>
            <label class="st-label">Warranty expiry <input v-model="form.warranty_expires_at" class="st-input" type="date"></label>
        </div>
        <label class="st-label">Notes <textarea v-model="form.notes" class="st-input" rows="3" /></label>
        <CustomFieldInputs v-model="form.custom_fields" :schema="customFieldSchema" :errors="form.errors" />
        <Button type="submit" variant="primary" :loading="form.processing">Save asset</Button>
    </form>
</template>
