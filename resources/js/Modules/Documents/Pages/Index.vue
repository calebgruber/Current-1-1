<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import CustomFieldInputs from '@/Components/custom-fields/CustomFieldInputs.vue';
import { http } from '@/lib/http';
import { usePermissions } from '@/composables/usePermissions';
import type { CustomFieldSchema } from '@/types';

type Option = { value: string; label: string };
const props = defineProps<{
    documents: { data: { id: number; number: string; title: string; type_label: string; entity_label: string }[]; links: { url: string | null; label: string; active: boolean }[] };
    templates: { id: number; name: string; entity_type: string; type_label: string }[];
    entityTypes: Option[];
    customFieldSchema: CustomFieldSchema[];
}>();
const { can } = usePermissions();
const form = useForm({ template_id: '', entity_type: '', entity_id: '', custom_fields: {} as Record<string, any> });
const templates = computed(() => props.templates.filter(t => t.entity_type === form.entity_type));
const query = ref('');
const entities = ref<{ value: number; label: string }[]>([]);
const lookupError = ref('');
const lookup = async () => {
    if (!form.entity_type) return;
    const type = form.entity_type;
    try {
        const response = await http.get(route('documents.entities'), { params: { type, q: query.value } });
        if (type !== form.entity_type) return;
        entities.value = response.data;
        lookupError.value = '';
    } catch { if (type === form.entity_type) lookupError.value = 'Could not load records.'; }
};
</script>

<template>
    <PageHeader title="Documents" description="Generate and review document snapshots.">
        <template #actions><Button v-if="can('documents.templates.view')" :href="route('documents.templates.index')">Templates</Button></template>
    </PageHeader>
    <form v-if="can('documents.documents.create')" class="st-card mb-6 space-y-3 p-4" @submit.prevent="form.post(route('documents.store'))">
        <h2 class="font-semibold">Generate a document</h2>
        <label class="block">Record type
            <select v-model="form.entity_type" required class="block w-full rounded border p-2" @change="form.template_id = ''; form.entity_id = ''; entities = []; lookup()">
                <option value="">Select a type</option><option v-for="type in entityTypes" :key="type.value" :value="type.value">{{ type.label }}</option>
            </select>
        </label>
        <label class="block">Template
            <select v-model="form.template_id" required class="block w-full rounded border p-2">
                <option value="">Select a template</option><option v-for="t in templates" :key="t.id" :value="t.id">{{ t.name }} · {{ t.type_label }}</option>
            </select>
        </label>
        <label class="block">Find a record<input v-model="query" class="block w-full rounded border p-2" @keydown.enter.prevent="lookup"></label>
        <Button :disabled="!form.entity_type" @click="lookup">Search records</Button>
        <p v-if="lookupError" class="text-red-600">{{ lookupError }}</p>
        <label class="block">Record<select v-model="form.entity_id" required class="block w-full rounded border p-2"><option value="">Select a record</option><option v-for="entity in entities" :key="entity.value" :value="String(entity.value)">{{ entity.label }}</option></select></label>
        <CustomFieldInputs v-model="form.custom_fields" :schema="customFieldSchema" :errors="form.errors" />
        <p v-for="(error, key) in form.errors" :key="key" class="text-sm text-red-600">{{ error }}</p>
        <Button type="submit" variant="primary" :loading="form.processing">Generate</Button>
    </form>
    <div class="st-card p-4">
        <p v-if="!documents.data.length">No documents found.</p>
        <ul v-else class="divide-y divide-line">
            <li v-for="d in documents.data" :key="d.id" class="py-3"><Link :href="route('documents.show', d.id)" class="st-link font-medium">{{ d.number }} · {{ d.title }}</Link><p class="text-sm text-muted">{{ d.type_label }} · {{ d.entity_label }}</p></li>
        </ul>
        <nav v-if="documents.links.length > 3" aria-label="Pagination" class="mt-4 flex flex-wrap gap-2"><template v-for="(link, i) in documents.links" :key="i"><Link v-if="link.url" :href="link.url" :aria-current="link.active ? 'page' : undefined" class="st-link" v-html="link.label" /><span v-else class="text-muted" v-html="link.label" /></template></nav>
    </div>
</template>
