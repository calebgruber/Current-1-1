<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import CustomFieldInputs from '@/Components/custom-fields/CustomFieldInputs.vue';
import type { CustomFieldSchema, Option } from '@/types';

interface Location {
    id: number; name: string; parent_id: number | null; code: string | null; type: string;
    barcode: string | null; address: string | null; notes: string | null; is_active: boolean;
}

const props = defineProps<{ location: Location | null; defaultParentId: number | null; parentOptions: Option[]; types: Option[]; customFieldSchema: CustomFieldSchema[]; customFields: Record<string, any> }>();
const location = props.location;
const form = useForm({
    name: location?.name ?? '', parent_id: location?.parent_id ?? props.defaultParentId ?? null,
    code: location?.code ?? '', type: location?.type ?? 'warehouse', barcode: location?.barcode ?? '',
    address: location?.address ?? '', notes: location?.notes ?? '', is_active: location?.is_active ?? true,
    custom_fields: { ...props.customFields },
});
const submit = () => location ? form.put(route('inventory.locations.update', location.id)) : form.post(route('inventory.locations.store'));
</script>

<template>
    <PageHeader :title="location ? 'Edit location' : 'New location'" />
    <form class="st-card max-w-3xl space-y-4 p-5" @submit.prevent="submit">
        <div v-if="Object.keys(form.errors).length" class="text-sm text-red-600" role="alert"><p v-for="(error, key) in form.errors" :key="key">{{ error }}</p></div>
        <div class="grid gap-4 sm:grid-cols-2">
            <label class="st-label">Name <input v-model="form.name" class="st-input" maxlength="255" required></label>
            <label class="st-label">Parent location <select v-model="form.parent_id" class="st-input"><option :value="null">None</option><option v-for="option in parentOptions" :key="String(option.value)" :value="option.value">{{ option.label }}</option></select></label>
            <label class="st-label">Type <select v-model="form.type" class="st-input" required><option v-for="option in types" :key="String(option.value)" :value="option.value">{{ option.label }}</option></select></label>
            <label class="st-label">Code <input v-model="form.code" class="st-input" maxlength="64"></label>
            <label class="st-label">Barcode <input v-model="form.barcode" class="st-input" maxlength="128"></label>
        </div>
        <label class="st-label">Address <textarea v-model="form.address" class="st-input" rows="3" maxlength="1000" /></label>
        <label class="st-label">Notes <textarea v-model="form.notes" class="st-input" rows="3" maxlength="5000" /></label>
        <label class="flex items-center gap-2 text-sm"><input v-model="form.is_active" type="checkbox"> Active</label>
        <CustomFieldInputs v-model="form.custom_fields" :schema="customFieldSchema" :errors="form.errors" />
        <div class="flex items-center gap-3"><Button type="submit" variant="primary" :loading="form.processing">Save location</Button><Link :href="location ? route('inventory.locations.show', location.id) : route('inventory.locations.index')" class="text-sm text-accent hover:underline">Cancel</Link></div>
    </form>
</template>
