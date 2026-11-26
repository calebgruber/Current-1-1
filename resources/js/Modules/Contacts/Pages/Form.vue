<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import Combobox from '@/Components/ui/Combobox.vue';
import CustomFieldInputs from '@/Components/custom-fields/CustomFieldInputs.vue';
import type { CustomFieldSchema } from '@/types';
const props = defineProps<{ contact: Record<string, any> | null; defaults: { type: string; organization: { value: number; label: string } | null }; customFieldSchema: CustomFieldSchema[]; customFields: Record<string, any> }>();
const c = props.contact;
const form = useForm({ type: c?.type ?? props.defaults.type, name: c?.name ?? '', organization_id: c?.organization_id ?? props.defaults.organization?.value ?? null, email: c?.email ?? '', phone: c?.phone ?? '', title: c?.title ?? '', address: c?.address ?? '', notes: c?.notes ?? '', custom_fields: { ...props.customFields } });
const submit = () => c ? form.put(`/contacts/${c.id}`) : form.post('/contacts');
</script>
<template>
    <PageHeader :title="c ? 'Edit contact' : 'New contact'" />
    <form class="st-card max-w-3xl space-y-4 p-5" @submit.prevent="submit">
        <div v-if="Object.keys(form.errors).length" class="text-sm text-red-600" role="alert"><p v-for="(error, key) in form.errors" :key="key">{{ error }}</p></div>
        <div class="grid gap-4 sm:grid-cols-2">
            <label class="st-label">Type <select v-model="form.type" class="st-input"><option value="person">Person</option><option value="organization">Organization</option></select></label>
            <label class="st-label">Name <input v-model="form.name" class="st-input" required></label>
            <div v-if="form.type === 'person'"><label class="st-label">Organization</label><Combobox v-model="form.organization_id" endpoint="/contacts/lookup?type=organization" :initial-label="defaults.organization?.label" placeholder="Find an organization" /></div>
            <label class="st-label">Title <input v-model="form.title" class="st-input"></label>
            <label class="st-label">Email <input v-model="form.email" class="st-input" type="email"></label>
            <label class="st-label">Phone <input v-model="form.phone" class="st-input"></label>
        </div>
        <label class="st-label">Address <textarea v-model="form.address" class="st-input" rows="2" /></label>
        <label class="st-label">Notes <textarea v-model="form.notes" class="st-input" rows="3" /></label>
        <CustomFieldInputs v-model="form.custom_fields" :schema="customFieldSchema" :errors="form.errors" />
        <Button type="submit" variant="primary" :loading="form.processing">Save contact</Button>
    </form>
</template>
