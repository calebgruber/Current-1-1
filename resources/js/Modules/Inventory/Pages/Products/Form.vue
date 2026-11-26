<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Boxes, Link2, PackagePlus, Plus, Tags, Trash2 } from 'lucide-vue-next';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import Combobox from '@/Components/ui/Combobox.vue';
import CustomFieldInputs from '@/Components/custom-fields/CustomFieldInputs.vue';
import type { CustomFieldSchema, Option } from '@/types';
import { relationshipPayload, relationshipSnapshot } from './relationshipPayload';

type Accessory = { key: number; accessory_product_id: number | string | null; name: string; quantity: number | string; type: string; auto_add: boolean; notes: string | null };
type Alternative = { key: number; product_id: number | string | null; name: string };
const props = defineProps<{ product: Record<string, any> | null; categories: Option[]; manufacturers: Option[]; locations: Option[]; trackingOptions: Option[]; tags: string[]; accessoryTypes: Option[]; canViewProducts: boolean; customFieldSchema: CustomFieldSchema[]; customFields: Record<string, any> }>();
const p = props.product;
let nextKey = 0;
const tagInput = ref('');
const form = useForm({
    name: p?.name ?? '', sku: p?.sku ?? '', barcode: p?.barcode ?? '', model: p?.model ?? '',
    description: p?.description ?? '', tracking: p?.tracking ?? 'bulk', category_id: p?.category_id ?? null,
    manufacturer_id: p?.manufacturer_id ?? null, default_location_id: p?.default_location_id ?? null,
    replacement_cost: p?.replacement_cost ?? '', rental_price: p?.rental_price ?? '', weight_kg: p?.weight_kg ?? '',
    is_active: p?.is_active ?? true, tags: [...(p?.tags ?? [])] as string[],
    accessories: (p?.accessories ?? []).map((a: Accessory) => ({ ...a, key: nextKey++ })) as Accessory[],
    alternatives: (p?.alternatives ?? []).map((a: Alternative) => ({ ...a, key: nextKey++ })) as Alternative[],
    custom_fields: { ...props.customFields },
});
const initialRelationships = relationshipSnapshot(form);
const addTag = () => {
    const tag = tagInput.value.trim();
    if (tag && !form.tags.some((existing) => existing.toLowerCase() === tag.toLowerCase())) form.tags.push(tag);
    tagInput.value = '';
};
const submit = () => {
    addTag();
    form.transform((data) => relationshipPayload(data, initialRelationships, !!p, props.canViewProducts));
    p ? form.put(`/inventory/products/${p.id}`) : form.post('/inventory/products');
};
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
            <label class="st-label">Category <select v-model="form.category_id" class="st-input"><option :value="null">None</option><option v-for="o in categories" :key="o.value" :value="o.value">{{ o.label }}</option></select></label>
            <label class="st-label">Manufacturer <select v-model="form.manufacturer_id" class="st-input"><option :value="null">None</option><option v-for="o in manufacturers" :key="o.value" :value="o.value">{{ o.label }}</option></select></label>
            <label class="st-label">Default location <select v-model="form.default_location_id" class="st-input"><option :value="null">None</option><option v-for="o in locations" :key="String(o.value)" :value="o.value">{{ o.label }}</option></select></label>
            <label class="st-label">Replacement cost <input v-model="form.replacement_cost" class="st-input" type="number" min="0" step="0.01"></label>
            <label class="st-label">Rental price <input v-model="form.rental_price" class="st-input" type="number" min="0" step="0.01"></label>
            <label class="st-label">Weight (kg) <input v-model="form.weight_kg" class="st-input" type="number" min="0" step="0.001"></label>
        </div>
        <fieldset class="space-y-2" :disabled="!!p" aria-describedby="tracking-help">
            <legend class="st-label">Tracking</legend>
            <label v-for="o in trackingOptions" :key="o.value" class="flex items-center gap-2 text-sm"><input v-model="form.tracking" type="radio" :value="o.value"> {{ o.label }}</label>
        </fieldset>
        <p id="tracking-help" class="text-sm text-muted">Bulk tracks quantities by location; serialized tracks individual assets and their serial numbers. Choose carefully: tracking cannot be changed after creation.</p>
        <label class="st-label">Description <textarea v-model="form.description" class="st-input" rows="3" /></label>
        <label class="flex items-center gap-2 text-sm"><input v-model="form.is_active" type="checkbox"> Active</label>
        <section class="space-y-2" aria-labelledby="tags-heading">
            <h2 id="tags-heading" class="flex items-center gap-2 font-semibold"><Tags class="size-4" aria-hidden="true" /> Tags</h2>
            <div class="flex flex-wrap gap-2">
                <span v-for="(tag, i) in form.tags" :key="i" class="flex items-center gap-1 rounded border border-line px-2 py-1 text-sm">{{ tag }} <button type="button" :aria-label="`Remove tag ${tag}`" @click="form.tags.splice(i, 1)"><Trash2 class="size-4" aria-hidden="true" /></button></span>
            </div>
            <div class="flex gap-2"><label class="st-label flex-1">Add a tag <input v-model="tagInput" list="product-tags" class="st-input" maxlength="50" @keydown.enter.prevent="addTag"></label><button type="button" class="st-btn self-end" @click="addTag"><Plus class="size-4" aria-hidden="true" /> Add tag</button></div>
            <datalist id="product-tags"><option v-for="tag in tags" :key="tag" :value="tag" /></datalist>
        </section>
        <template v-if="canViewProducts">
            <section class="space-y-3" aria-labelledby="accessories-heading">
                <h2 id="accessories-heading" class="flex items-center gap-2 font-semibold"><PackagePlus class="size-4" aria-hidden="true" /> Accessories</h2>
                <p class="text-sm text-muted">Products associated with this parent product. Quantity is per parent unit; auto-add controls inclusion when accessories are requested.</p>
                <div v-for="(row, i) in form.accessories" :key="row.key" class="space-y-2 rounded border border-line p-3">
                    <label class="st-label">Accessory product <Combobox v-model="row.accessory_product_id" endpoint="/inventory/products/lookup" :initial-label="row.name" placeholder="Find a product" /></label>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <label class="st-label">Quantity per parent <input v-model="row.quantity" class="st-input" type="number" min="0.01" max="10000" step="0.01" required></label>
                        <label class="st-label">Type <select v-model="row.type" class="st-input"><option v-for="o in accessoryTypes" :key="o.value" :value="o.value">{{ o.label }}</option></select></label>
                    </div>
                    <label class="st-label">Notes <input v-model="row.notes" class="st-input" maxlength="255"></label>
                    <label class="flex items-center gap-2 text-sm"><input v-model="row.auto_add" type="checkbox"> Auto-add</label>
                    <button type="button" class="st-btn" :aria-label="`Remove accessory ${row.name || i + 1}`" @click="form.accessories.splice(i, 1)"><Trash2 class="size-4" aria-hidden="true" /> Remove</button>
                </div>
                <button type="button" class="st-btn" @click="form.accessories.push({ key: nextKey++, accessory_product_id: null, name: '', quantity: 1, type: 'required', auto_add: true, notes: '' })"><Plus class="size-4" aria-hidden="true" /> Add accessory</button>
            </section>
            <section class="space-y-3" aria-labelledby="alternatives-heading">
                <h2 id="alternatives-heading" class="flex items-center gap-2 font-semibold"><Link2 class="size-4" aria-hidden="true" /> Alternative products</h2>
                <p class="text-sm text-muted">Explicit suggestions for this product only. They are not reciprocal and are never substituted automatically.</p>
                <div v-for="(row, i) in form.alternatives" :key="row.key" class="flex items-end gap-2">
                    <label class="st-label flex-1">Alternative product <Combobox v-model="row.product_id" endpoint="/inventory/products/lookup" :initial-label="row.name" placeholder="Find a product" /></label>
                    <button type="button" class="st-btn" :aria-label="`Remove alternative ${row.name || i + 1}`" @click="form.alternatives.splice(i, 1)"><Trash2 class="size-4" aria-hidden="true" /> Remove</button>
                </div>
                <button type="button" class="st-btn" @click="form.alternatives.push({ key: nextKey++, product_id: null, name: '' })"><Plus class="size-4" aria-hidden="true" /> Add alternative</button>
            </section>
        </template>
        <p v-else class="flex items-center gap-2 text-sm text-muted"><Boxes class="size-4" aria-hidden="true" /> Product view permission is required to edit accessories and alternatives.</p>
        <CustomFieldInputs v-model="form.custom_fields" :schema="customFieldSchema" :errors="form.errors" />
        <Button type="submit" variant="primary" :loading="form.processing">Save product</Button>
    </form>
</template>
