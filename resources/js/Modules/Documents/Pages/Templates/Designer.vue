<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { http } from '@/lib/http';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import BlockEditor from './BlockEditor.vue';

type Template = { id: number | null; name: string; type: string; entity_type: string; editor: string; blocks: any[]; html: string; css: string; paper_size: string; orientation: string; margins: Record<string, number | null> | null; watermark: string | null; is_default: boolean; version: number };
const props = defineProps<{ template: Template; types: { value: string; label: string }[]; paperSizes: string[]; canAdmin: boolean; variables: Record<string, string[]> }>();
const form = useForm({ ...props.template, blocks: JSON.parse(JSON.stringify(props.template.blocks)), margins: props.template.margins ? { ...props.template.margins } : null });
const previewHtml = ref('');
const previewError = ref('');
const previewLoading = ref(false);
const compileError = ref('');
const compiling = ref(false);
const zoom = ref(75);
const margins = ['top', 'right', 'bottom', 'left'] as const;
const defaults: Record<string, number> = { top: 14, right: 12, bottom: 14, left: 12 };
const sizes: Record<string, [number, number]> = { letter: [215.9, 279.4], legal: [215.9, 355.6], a4: [210, 297], a3: [297, 420], tabloid: [279.4, 431.8] };
const dimensions = computed(() => {
    const [w, h] = sizes[form.paper_size] ?? sizes.letter!;
    return form.orientation === 'landscape' ? [h, w] : [w, h];
});
const pageStyle = computed(() => ({
    width: `${dimensions.value[0]}mm`, height: `${dimensions.value[1]}mm`,
    padding: margins.map(side => `${form.margins?.[side] ?? defaults[side]}mm`).join(' '),
    transform: `scale(${zoom.value / 100})`, transformOrigin: 'top left',
}));
const pageSpaceStyle = computed(() => ({
    width: `${(dimensions.value[0] ?? 0) * zoom.value / 100}mm`,
    height: `${(dimensions.value[1] ?? 0) * zoom.value / 100}mm`,
}));
const draft = computed(() => ({
    type: form.type, entity_type: form.entity_type, editor: form.editor, blocks: form.blocks,
    html: form.html, css: form.css, paper_size: form.paper_size, orientation: form.orientation,
    margins: form.margins, watermark: form.watermark,
}));
let revision = 0;
let timer: ReturnType<typeof setTimeout> | undefined;
let controller: AbortController | undefined;

function message(error: unknown): string {
    if (axios.isAxiosError(error)) {
        const data = error.response?.data;
        if (error.response?.status === 403) return 'You do not have permission to preview or compile templates.';
        if (error.response?.status === 419) return 'Your session expired. Reload the page and try again.';
        if (error.response?.status === 429) return 'Too many requests. Wait a moment and try again.';
        if (data?.errors) return Object.values(data.errors).flat().join(' ');
        return data?.message || 'The request failed. Check your connection and try again.';
    }
    return 'The request failed. Check your connection and try again.';
}
async function preview(current: number) {
    controller = new AbortController();
    previewLoading.value = true;
    try {
        const { data } = await http.post(route('documents.templates.preview'), draft.value, { signal: controller.signal });
        if (current !== revision) return;
        if (data.error) {
            previewError.value = `${data.error.line ? `Line ${data.error.line}: ` : ''}${data.error.message}`;
            previewHtml.value = '';
        } else {
            previewError.value = '';
            previewHtml.value = (data.html || '').replace('<head>', `<head><meta http-equiv="Content-Security-Policy" content="default-src 'none'; style-src 'unsafe-inline'; img-src data: ${window.location.origin}">`);
        }
    } catch (error) {
        if (current !== revision || axios.isCancel(error)) return;
        previewHtml.value = '';
        previewError.value = message(error);
    } finally {
        if (current === revision) previewLoading.value = false;
    }
}
watch(() => JSON.stringify(draft.value), () => {
    revision++;
    controller?.abort();
    clearTimeout(timer);
    previewLoading.value = true;
    const current = revision;
    timer = setTimeout(() => preview(current), 500);
}, { immediate: true });
watch(() => form.type, type => {
    if (props.template.is_default && type === props.template.type) form.is_default = true;
});
onBeforeUnmount(() => { revision++; controller?.abort(); clearTimeout(timer); });

function setMargin(side: string, value: string) {
    form.margins ??= {};
    form.margins[side] = value === '' ? null : Number(value);
}
async function compileToCode() {
    if (form.html && !window.confirm('Replace the current HTML with the compiled blocks?')) return;
    compiling.value = true;
    compileError.value = '';
    const blocksAtStart = JSON.stringify(form.blocks);
    const htmlAtStart = form.html;
    const editorAtStart = form.editor;
    try {
        const { data } = await http.post(route('documents.templates.compile'), { blocks: form.blocks });
        if (blocksAtStart !== JSON.stringify(form.blocks) || htmlAtStart !== form.html || editorAtStart !== form.editor) {
            compileError.value = 'The editor changed while compiling. Please try again.';
        } else if (data.error) {
            compileError.value = `${data.error.line ? `Line ${data.error.line}: ` : ''}${data.error.message}`;
        } else {
            form.html = data.source ?? '';
            form.editor = 'code';
        }
    } catch (error) {
        compileError.value = message(error);
    } finally {
        compiling.value = false;
    }
}
function setEditor(mode: string) {
    if (mode === form.editor) return;
    if (mode === 'visual' && form.html && !window.confirm('Saving in visual mode removes the saved HTML. Your blocks will be kept. Continue?')) return;
    if (mode === 'code' && !form.html && !window.confirm('The HTML editor is empty. Compile blocks to HTML if you want to use this visual layout in code mode. Continue with empty HTML?')) return;
    form.editor = mode;
}
function save() {
    if (form.id) form.put(route('documents.templates.update', form.id), { preserveScroll: true });
    else form.post(route('documents.templates.store'));
}
</script>

<template>
    <PageHeader :title="template.id ? 'Edit template' : 'New template'">
        <template #actions><Button :href="route('documents.templates.index')">Templates</Button><Button type="submit" form="designer-form" variant="primary" :loading="form.processing">Save template</Button></template>
    </PageHeader>
    <form id="designer-form" class="designer-layout" @submit.prevent="save">
        <section class="designer-sidebar st-card p-4" aria-label="Template editor">
            <div class="space-y-4">
                <div>
                    <h2 class="font-semibold">Template details</h2>
                    <label class="mt-2 block text-sm">Name<input v-model="form.name" required maxlength="255" class="designer-input"></label>
                    <label class="mt-2 block text-sm">Type<select v-model="form.type" class="designer-input"><option v-for="t in types" :key="t.value" :value="t.value">{{ t.label }}</option></select></label>
                    <label class="mt-2 block text-sm">Record type<select v-model="form.entity_type" class="designer-input"><option value="job">Job</option><option value="maintenance">Maintenance</option></select></label>
                    <label v-if="canAdmin" class="mt-2 block text-sm"><input v-model="form.is_default" type="checkbox" :disabled="template.is_default && form.type === template.type"> Make default for this type</label>
                    <p v-if="canAdmin && template.is_default && form.type === template.type" class="mt-1 text-xs text-muted">To replace this default, select another template as the default.</p>
                    <p v-else-if="canAdmin && template.is_default" class="mt-1 text-xs text-muted">Changing type removes this template as the old default. If checked, it also replaces the new type's default; uncheck to leave that default unchanged.</p>
                </div>
                <div class="border-t border-line pt-4">
                    <h2 class="font-semibold">Page setup</h2>
                    <div class="mt-2 grid grid-cols-2 gap-2 text-sm">
                        <label>Paper size<select v-model="form.paper_size" class="designer-input"><option v-for="size in paperSizes" :key="size" :value="size">{{ size.toUpperCase() }}</option></select></label>
                        <label>Orientation<select v-model="form.orientation" class="designer-input"><option value="portrait">Portrait</option><option value="landscape">Landscape</option></select></label>
                        <label v-for="side in margins" :key="side" class="capitalize">{{ side }} margin (mm)<input type="number" min="0" max="50" step="0.1" :value="form.margins?.[side] ?? ''" :placeholder="String(defaults[side])" class="designer-input" @input="setMargin(side, ($event.target as HTMLInputElement).value)"></label>
                    </div>
                    <p class="mt-1 text-xs text-muted">Blank margins use the default shown. Range: 0–50 mm.</p>
                    <label class="mt-2 block text-sm">Watermark<input v-model="form.watermark" maxlength="120" class="designer-input"></label>
                </div>
                <div class="border-t border-line pt-4">
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="font-semibold">Content</h2>
                        <div class="flex gap-1">
                            <button type="button" class="rounded px-2 py-1 text-sm" :class="form.editor === 'visual' ? 'bg-accent text-white' : 'border border-line'" :aria-pressed="form.editor === 'visual'" @click="setEditor('visual')">Visual</button>
                            <button type="button" class="rounded px-2 py-1 text-sm" :class="form.editor === 'code' ? 'bg-accent text-white' : 'border border-line'" :aria-pressed="form.editor === 'code'" @click="setEditor('code')">Code</button>
                        </div>
                    </div>
                    <div v-if="form.editor === 'visual'" class="mt-3">
                        <BlockEditor :blocks="form.blocks" />
                        <button type="button" class="mt-3 rounded border border-line px-3 py-2 text-sm" :disabled="compiling" @click="compileToCode">{{ compiling ? 'Compiling…' : 'Compile blocks to HTML' }}</button>
                        <p class="mt-1 text-xs text-muted">Compilation replaces HTML and switches to code; blocks remain available.</p>
                    </div>
                    <label v-else class="mt-3 block text-sm">HTML template<textarea v-model="form.html" rows="18" class="designer-input font-mono" /></label>
                    <p v-if="compileError" class="mt-2 text-sm text-red-600" role="alert">{{ compileError }}</p>
                    <label class="mt-3 block text-sm">Custom CSS<textarea v-model="form.css" rows="5" class="designer-input font-mono" /></label>
                    <details class="mt-3 text-sm"><summary class="cursor-pointer">Available variables</summary><div v-for="(items, group) in variables" :key="group" class="mt-2"><strong>{{ group }}</strong><p class="break-words text-xs text-muted">{{ items.join(', ') }}</p></div></details>
                </div>
                <div v-if="Object.keys(form.errors).length" class="text-sm text-red-600" role="alert"><p v-for="(error, key) in form.errors" :key="key">{{ key }}: {{ error }}</p></div>
                <Button type="submit" variant="primary" :loading="form.processing">Save template</Button>
            </div>
        </section>
        <section class="designer-preview st-card" aria-label="Document preview">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line p-4">
                <div><h2 class="font-semibold">Page preview</h2><p class="text-xs text-muted">Sample data · screen preview is not paginated; content may be clipped and page breaks are not shown.</p></div>
                <label class="flex items-center gap-2 text-sm">Zoom <input v-model.number="zoom" type="range" min="25" max="150" step="5" aria-label="Preview zoom"><span class="w-10">{{ zoom }}%</span></label>
            </div>
            <div class="designer-canvas">
                <p v-if="previewError" class="mb-3 max-w-xl rounded bg-red-50 p-3 text-sm text-red-700" role="alert">{{ previewError }}</p>
                <p v-if="previewLoading" class="mb-3 text-sm text-muted" role="status">Updating preview…</p>
                <div v-if="previewHtml" :style="pageSpaceStyle" class="mx-auto">
                    <div class="designer-page" :style="pageStyle">
                        <iframe title="Sanitized document preview" sandbox="" :srcdoc="previewHtml" class="h-full w-full border-0" />
                    </div>
                </div>
            </div>
        </section>
    </form>
</template>

<style>
.designer-input { display: block; width: 100%; margin-top: .25rem; border: 1px solid var(--color-line, #d4d4d8); border-radius: .375rem; padding: .4rem .5rem; background: transparent; }
.designer-layout { display: grid; grid-template-columns: minmax(300px, 430px) minmax(0, 1fr); gap: 1rem; align-items: start; }
.designer-sidebar { min-width: 0; }
.designer-preview { min-width: 0; overflow: hidden; }
.designer-canvas { overflow: auto; min-height: 650px; padding: 2rem; background: #e4e4e7; }
.designer-page { box-sizing: border-box; background: white; box-shadow: 0 4px 18px #0002; }
@media (max-width: 900px) { .designer-layout { grid-template-columns: minmax(0, 1fr); } .designer-canvas { min-height: 450px; padding: 1rem; } }
</style>
