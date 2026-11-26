<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { Pencil, Plus } from 'lucide-vue-next';
import { ref } from 'vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { usePermissions } from '@/composables/usePermissions';

type Field = { id: number; key: string; label: string; type: string; is_required: boolean; help_text: string | null };
defineProps<{ fields: Field[]; types: { value: string; label: string }[] }>();
const { can } = usePermissions();
const form = useForm({ key: '', label: '', type: 'text', help_text: '', is_required: false, options: [] as string[] });
const optionsText = ref('');
const editing = ref<number | null>(null);
const editForm = useForm({ label: '', help_text: '' });
const startEdit = (field: Field) => {
    editForm.clearErrors();
    editForm.label = field.label;
    editForm.help_text = field.help_text ?? '';
    editing.value = field.id;
};
const saveEdit = () => {
    if (editing.value === null) return;
    editForm.put(route('settings.custom-fields.update', editing.value), { preserveScroll: true, onSuccess: () => { editing.value = null; } });
};
const submit = () => {
    form.options = ['dropdown', 'multi_select'].includes(form.type) ? optionsText.value.split('\n').map((option) => option.trim()).filter(Boolean) : [];
    form.post(route('settings.custom-fields.store'), { onSuccess: () => { form.reset(); optionsText.value = ''; } });
};
</script>

<template>
    <PageHeader title="Job custom fields" description="Define additional information to capture on jobs.">
        <template #actions><Link v-if="can('jobs.jobs.view')" class="st-link" :href="route('jobs.index')">All jobs</Link></template>
    </PageHeader>
    <div class="space-y-5">
        <section class="st-card space-y-3 p-4">
            <h2 class="font-semibold">Existing job fields</h2>
            <p class="text-sm text-muted">Only labels and help text can be edited. Keys, types and options cannot be changed here; existing values are preserved. Other entity types are not managed on this page.</p>
            <p v-if="!fields.length">No custom fields configured for jobs.</p>
            <ul v-else class="divide-y divide-line">
                <li v-for="field in fields" :key="field.id" class="space-y-2 py-2">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div><span class="font-medium">{{ field.label }}</span> <span class="text-sm text-muted">· {{ field.key }} · {{ field.type }}{{ field.is_required ? ' · Required' : '' }}</span><p v-if="field.help_text" class="text-sm text-muted">{{ field.help_text }}</p></div>
                        <button v-if="can('settings.custom_fields.edit') && editing !== field.id" type="button" class="st-link inline-flex items-center gap-1" @click="startEdit(field)"><Pencil class="size-4" aria-hidden="true" /> Edit <span class="sr-only">{{ field.label }}</span></button>
                    </div>
                    <form v-if="editing === field.id && can('settings.custom_fields.edit')" class="space-y-3" @submit.prevent="saveEdit">
                        <div v-if="Object.keys(editForm.errors).length" role="alert" class="text-red-600"><p v-for="(error, key) in editForm.errors" :key="key">{{ error }}</p></div>
                        <label class="block">Label <input v-model="editForm.label" class="st-input" required maxlength="255"></label>
                        <label class="block">Help text <input v-model="editForm.help_text" class="st-input" maxlength="255"></label>
                        <div class="flex gap-2"><button type="submit" class="st-btn" :disabled="editForm.processing">Save</button><button type="button" class="st-btn" :disabled="editForm.processing" @click="editing = null">Cancel</button></div>
                    </form>
                </li>
            </ul>
        </section>
        <form v-if="can('settings.custom_fields.create')" class="st-card max-w-3xl space-y-4 p-4" @submit.prevent="submit">
            <h2 class="flex items-center gap-2 font-semibold"><Plus class="size-4" aria-hidden="true" /> Add job field</h2>
            <div v-if="Object.keys(form.errors).length" role="alert" class="text-red-600"><p v-for="(error, key) in form.errors" :key="key">{{ error }}</p></div>
            <label class="block">Label <input v-model="form.label" class="st-input" required maxlength="255"></label>
            <label class="block">Key <input v-model="form.key" class="st-input" required maxlength="64" pattern="[a-z][a-z0-9_]*" aria-describedby="key-hint"><span id="key-hint" class="text-sm text-muted">Start with a lowercase letter; use lowercase letters, numbers and underscores only. Keys cannot be changed later.</span></label>
            <label class="block">Type <select v-model="form.type" class="st-input"><option v-for="type in types" :key="type.value" :value="type.value">{{ type.label }}</option></select></label>
            <label v-if="form.type === 'dropdown' || form.type === 'multi_select'" class="block">Options, one per line <textarea v-model="optionsText" class="st-input" rows="4" required /></label>
            <label class="block">Help text <input v-model="form.help_text" class="st-input" maxlength="255"></label>
            <label v-if="form.type !== 'checkbox'"><input v-model="form.is_required" type="checkbox"> Required</label>
            <p class="text-sm text-muted">A required field must be filled in when creating or editing a job, including existing jobs.</p>
            <button type="submit" class="st-btn" :disabled="form.processing">Add field</button>
        </form>
    </div>
</template>
<style scoped>.st-btn { border: 1px solid var(--st-line); border-radius: .375rem; padding: .375rem .75rem; background: var(--st-surface); } .st-btn:hover { background: var(--st-hover); } .st-btn:disabled { opacity: .5; }</style>
