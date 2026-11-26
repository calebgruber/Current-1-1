<script setup lang="ts">
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';

type Template = { id: number | null; name: string; type: string; entity_type: string; editor: string; blocks: any[]; html: string; css: string; paper_size: string; orientation: string; margins: Record<string, number | null> | null; watermark: string | null; is_default: boolean };
const props = defineProps<{ template: Template; types: { value: string; label: string }[]; paperSizes: string[]; canAdmin: boolean }>();
const form = useForm({ ...props.template });
const blocks = ref(JSON.stringify(props.template.blocks, null, 2));
const error = ref('');
const save = () => {
    try { const parsed = JSON.parse(blocks.value); if (!Array.isArray(parsed)) throw new Error('Blocks must be an array.'); form.blocks = parsed; error.value = ''; }
    catch (e) { error.value = e instanceof Error ? e.message : 'Invalid blocks JSON.'; return; }
    if (form.id) form.put(route('documents.templates.update', form.id)); else form.post(route('documents.templates.store'));
};
</script>
<template>
    <PageHeader :title="template.id ? 'Edit template' : 'New template'"><template #actions><Button :href="route('documents.templates.index')">Templates</Button></template></PageHeader>
    <form class="st-card space-y-4 p-4" @submit.prevent="save">
        <label class="block">Name<input v-model="form.name" required maxlength="255" class="block w-full rounded border p-2"></label>
        <label class="block">Type<select v-model="form.type" class="block w-full rounded border p-2"><option v-for="t in types" :key="t.value" :value="t.value">{{ t.label }}</option></select></label>
        <label class="block">Record type<select v-model="form.entity_type" class="block w-full rounded border p-2"><option value="job">Job</option><option value="maintenance">Maintenance</option></select></label>
        <label class="block">Editor<select v-model="form.editor" class="block w-full rounded border p-2"><option value="visual">Blocks (JSON)</option><option value="code">HTML</option></select></label>
        <label v-if="form.editor === 'visual'" class="block">Blocks JSON<textarea v-model="blocks" rows="18" class="block w-full rounded border p-2 font-mono text-sm" /></label>
        <label v-else class="block">HTML<textarea v-model="form.html" rows="18" class="block w-full rounded border p-2 font-mono text-sm" /></label>
        <label class="block">CSS<textarea v-model="form.css" rows="5" class="block w-full rounded border p-2 font-mono text-sm" /></label>
        <label class="block">Paper size<select v-model="form.paper_size" class="block w-full rounded border p-2"><option v-for="size in paperSizes" :key="size" :value="size">{{ size }}</option></select></label>
        <label class="block">Orientation<select v-model="form.orientation" class="block w-full rounded border p-2"><option value="portrait">Portrait</option><option value="landscape">Landscape</option></select></label>
        <label v-if="canAdmin"><input v-model="form.is_default" type="checkbox"> Make default</label>
        <p v-if="error" class="text-red-600">{{ error }}</p><p v-for="(message, key) in form.errors" :key="key" class="text-red-600">{{ key }}: {{ message }}</p>
        <div><Button type="submit" variant="primary" :loading="form.processing">Save template</Button></div>
    </form>
</template>
