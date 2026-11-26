<script setup lang="ts">
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
type Rule = { id: number | null; name: string; description: string; severity: string; is_active: boolean; definition: Record<string, any> };
const props = defineProps<{ rule: Rule; severities: string[]; presets: { key: string; name: string; description: string; severity: string; definition: Record<string, any> }[] }>();
const form = useForm({ ...props.rule });
const definition = ref(JSON.stringify(props.rule.definition, null, 2));
const error = ref('');
const preset = (key: string) => {
    const p = props.presets.find(item => item.key === key);
    if (p) { form.name = p.name; form.description = p.description; form.severity = p.severity; definition.value = JSON.stringify(p.definition, null, 2); }
};
const save = () => {
    try { const value: unknown = JSON.parse(definition.value); if (!value || Array.isArray(value) || typeof value !== 'object') throw new Error('Definition must be an object.'); form.definition = value as Record<string, any>; error.value = ''; }
    catch (e) { error.value = e instanceof Error ? e.message : 'Invalid definition JSON.'; return; }
    if (form.id) form.put(route('rules.update', form.id)); else form.post(route('rules.store'));
};
</script>
<template>
    <PageHeader :title="rule.id ? 'Edit rule' : 'New rule'"><template #actions><Button :href="route('rules.index')">Rules</Button></template></PageHeader>
    <form class="st-card space-y-4 p-4" @submit.prevent="save">
        <label class="block">Start from preset<select class="block w-full rounded border p-2" @change="preset(($event.target as HTMLSelectElement).value)"><option value="">Choose a preset</option><option v-for="p in presets" :key="p.key" :value="p.key">{{ p.name }}</option></select></label>
        <label class="block">Name<input v-model="form.name" required maxlength="255" class="block w-full rounded border p-2"></label>
        <label class="block">Description<textarea v-model="form.description" class="block w-full rounded border p-2" /></label>
        <label class="block">Severity<select v-model="form.severity" class="block w-full rounded border p-2"><option v-for="s in severities" :key="s" :value="s">{{ s }}</option></select></label>
        <label class="block"><input v-model="form.is_active" type="checkbox"> Active</label>
        <label class="block">Rule definition JSON<textarea v-model="definition" rows="18" class="block w-full rounded border p-2 font-mono text-sm" /></label>
        <p v-if="error" class="text-red-600">{{ error }}</p><p v-for="(message, key) in form.errors" :key="key" class="text-red-600">{{ key }}: {{ message }}</p>
        <Button type="submit" variant="primary" :loading="form.processing">Save rule</Button>
    </form>
</template>
