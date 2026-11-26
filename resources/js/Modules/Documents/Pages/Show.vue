<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import CustomFieldInputs from '@/Components/custom-fields/CustomFieldInputs.vue';
import { usePermissions } from '@/composables/usePermissions';
import type { CustomFieldSchema } from '@/types';

const props = defineProps<{ document: { id: number; number: string; title: string; type_label: string; entity_label: string }; html: string; customFields: Record<string, any>; customFieldSchema: CustomFieldSchema[] }>();
const { can } = usePermissions();
const form = useForm({ title: props.document.title, custom_fields: props.customFields });
const remove = () => { if (confirm('Delete this document?')) form.delete(route('documents.destroy', props.document.id)); };
</script>
<template>
    <PageHeader :title="`${document.number} · ${document.title}`" :description="`${document.type_label} · ${document.entity_label}`">
        <template #actions>
            <Button :href="route('documents.index')">Documents</Button>
            <a :href="route('documents.pdf', document.id)" class="inline-flex h-8 items-center rounded-md border border-line bg-surface px-3 text-sm font-medium hover:bg-hover">View PDF</a>
            <a v-if="can('documents.documents.export')" :href="route('documents.pdf', { document: document.id, download: 1 })" class="inline-flex h-8 items-center rounded-md border border-line bg-surface px-3 text-sm font-medium hover:bg-hover">Download PDF</a>
            <Button v-if="can('documents.documents.delete')" variant="danger" @click="remove">Delete</Button>
        </template>
    </PageHeader>
    <form v-if="can('documents.documents.create')" class="st-card mb-4 flex flex-wrap items-end gap-3 p-4" @submit.prevent="form.put(route('documents.update', document.id))">
        <label class="grow">Title<input v-model="form.title" required maxlength="255" class="block w-full rounded border p-2"></label>
        <div class="w-full"><CustomFieldInputs v-model="form.custom_fields" :schema="customFieldSchema" :errors="form.errors" /></div>
        <Button type="submit" :loading="form.processing">Save document</Button>
        <p v-if="form.errors.title" class="text-red-600">{{ form.errors.title }}</p>
    </form>
    <iframe title="Document preview" :srcdoc="html" sandbox="" class="h-[75vh] w-full rounded border bg-white" />
</template>
