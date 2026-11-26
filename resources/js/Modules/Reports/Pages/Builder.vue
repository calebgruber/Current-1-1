<script setup lang="ts">
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
type Definition = { columns: string[]; [key: string]: any };
type Report = { id: number | null; name: string; description: string | null; source: string | null; definition: Definition; is_shared: boolean; role_ids: number[]; show_on_dashboard: boolean };
type Source = { key: string; label: string; columns: { key: string; label: string }[] };
const props = defineProps<{ report: Report; sources: Source[]; roles: { value: number; label: string }[] }>();
const form = useForm({ ...props.report, definition: JSON.parse(JSON.stringify(props.report.definition)) as Definition });
const source = computed(() => props.sources.find(s => s.key === form.source));
const advanced = ref(JSON.stringify(form.definition, null, 2));
const error = ref('');
const changeSource = () => { form.definition.columns = []; advanced.value = JSON.stringify(form.definition, null, 2); };
const syncAdvanced = () => {
    try { const parsed = JSON.parse(advanced.value); if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) return; parsed.columns = form.definition.columns; advanced.value = JSON.stringify(parsed, null, 2); } catch { /* Keep invalid draft for correction. */ }
};
const save = () => {
    try { const value: unknown = JSON.parse(advanced.value); if (!value || Array.isArray(value) || typeof value !== 'object') throw new Error('Definition must be an object.'); form.definition = value as Definition; error.value = ''; }
    catch (e) { error.value = e instanceof Error ? e.message : 'Invalid definition JSON.'; return; }
    if (form.id) form.put(route('reports.update', form.id)); else form.post(route('reports.store'));
};
</script>
<template>
    <PageHeader :title="report.id ? 'Edit report' : 'New report'"><template #actions><Button :href="route('reports.index')">Reports</Button></template></PageHeader>
    <form class="st-card space-y-4 p-4" @submit.prevent="save">
        <label class="block">Name<input v-model="form.name" required maxlength="255" class="block w-full rounded border p-2"></label>
        <label class="block">Description<textarea v-model="form.description" class="block w-full rounded border p-2" /></label>
        <label class="block">Source<select v-model="form.source" required class="block w-full rounded border p-2" @change="changeSource"><option :value="null">Select a source</option><option v-for="s in sources" :key="s.key" :value="s.key">{{ s.label }}</option></select></label>
        <fieldset v-if="source"><legend>Columns</legend><div class="grid gap-1 sm:grid-cols-2 lg:grid-cols-3"><label v-for="c in source.columns" :key="c.key"><input v-model="form.definition.columns" type="checkbox" :value="c.key" @change="syncAdvanced"> {{ c.label }}</label></div></fieldset>
        <label class="block">Definition JSON (advanced filters, grouping and presentation)<textarea v-model="advanced" rows="14" class="block w-full rounded border p-2 font-mono text-sm" /></label>
        <label class="block"><input v-model="form.is_shared" type="checkbox"> Share report</label>
        <fieldset v-if="form.is_shared"><legend>Restrict to roles (leave empty for all)</legend><label v-for="r in roles" :key="r.value" class="mr-3"><input v-model="form.role_ids" type="checkbox" :value="r.value"> {{ r.label }}</label></fieldset>
        <label class="block"><input v-model="form.show_on_dashboard" type="checkbox"> Show on dashboard</label>
        <p v-if="error" class="text-red-600">{{ error }}</p><p v-for="(message, key) in form.errors" :key="key" class="text-red-600">{{ key }}: {{ message }}</p>
        <Button type="submit" variant="primary" :loading="form.processing">Save report</Button>
    </form>
</template>
