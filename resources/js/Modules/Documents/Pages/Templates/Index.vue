<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import { usePermissions } from '@/composables/usePermissions';
defineProps<{ templates: { id: number; name: string; type_label: string; is_default: boolean; documents_count: number }[] }>();
const { can } = usePermissions();
const remove = (id: number) => { if (confirm('Delete this template?')) router.delete(route('documents.templates.destroy', id)); };
</script>
<template>
    <PageHeader title="Document templates"><template #actions><Button :href="route('documents.index')">Documents</Button><Button v-if="can('documents.templates.create')" variant="primary" :href="route('documents.templates.create')">New template</Button></template></PageHeader>
    <div class="st-card p-4">
        <p v-if="!templates.length">No templates found.</p>
        <ul class="divide-y divide-line">
            <li v-for="t in templates" :key="t.id" class="flex flex-wrap items-center justify-between gap-3 py-3">
                <div><p class="font-medium">{{ t.name }} <span v-if="t.is_default" class="text-sm text-muted">(default)</span></p><p class="text-sm text-muted">{{ t.type_label }} · {{ t.documents_count }} documents</p></div>
                <div class="flex flex-wrap gap-2"><Button v-if="can('documents.templates.edit')" :href="route('documents.templates.edit', t.id)">Edit</Button><Button v-if="can('documents.templates.create')" :href="route('documents.templates.duplicate', t.id)" method="post">Duplicate</Button><Button v-if="can('documents.templates.admin') && !t.is_default" :href="route('documents.templates.default', t.id)" method="post">Make default</Button><Button v-if="can('documents.templates.delete')" variant="danger" @click="remove(t.id)">Delete</Button></div>
            </li>
        </ul>
        <Button v-if="can('documents.templates.admin')" class="mt-4" @click="router.post(route('documents.templates.install-defaults'), { mode: 'missing' })">Install missing defaults</Button>
    </div>
</template>
