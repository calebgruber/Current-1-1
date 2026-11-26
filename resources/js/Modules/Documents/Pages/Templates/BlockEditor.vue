<script setup lang="ts">
import { ref } from 'vue';

defineOptions({ name: 'BlockEditor' });
type Block = { id?: string; type: string; props?: Record<string, any>; style?: Record<string, any>; [key: string]: any };
const props = defineProps<{ blocks: Block[]; depth?: number }>();
const newType = ref('heading');
const types = ['heading', 'text', 'image', 'logo', 'fields', 'items_table', 'totals', 'signature', 'spacer', 'divider', 'columns', 'conditional', 'page_break', 'html'];
const labels: Record<string, string> = { items_table: 'Items table', page_break: 'Page break' };
const formats = ['', 'currency', 'number', 'decimal', 'date', 'datetime', 'upper', 'multiline', 'yesno'];

function property(block: Block, key: string, value: any) {
    block.props ??= {};
    block.props[key] = value;
}
function styling(block: Block, key: string, value: any) {
    block.style ??= {};
    block.style[key] = value;
}
function add() {
    const defaults: Record<string, Record<string, any>> = {
        heading: { text: '', level: 2 }, text: { html: '<p></p>' }, image: { src: '', alt: '' },
        fields: { columns: 2, fields: [] }, items_table: { source: 'items', columns: [{ label: 'Item', value: 'item.name' }] },
        totals: { rows: [] }, signature: { lines: [{ label: 'Signature' }] }, spacer: { height: 16 },
        columns: { columns: [{ width: '50%', blocks: [] }, { width: '50%', blocks: [] }] },
        conditional: { condition: 'true', blocks: [] }, html: { html: '' },
    };
    props.blocks.push({ id: `b${Date.now().toString(36)}${Math.random().toString(36).slice(2, 8)}`, type: newType.value, props: defaults[newType.value] ?? {}, style: {} });
}
function move(index: number, direction: number) {
    const target = index + direction;
    if (target < 0 || target >= props.blocks.length) return;
    const item = props.blocks.splice(index, 1)[0];
    if (item) props.blocks.splice(target, 0, item);
}
function addRow(block: Block, key: string, value: Record<string, any>) {
    block.props ??= {};
    block.props[key] ??= [];
    block.props[key].push(value);
}
</script>

<template>
    <div class="space-y-3">
        <div v-for="(block, index) in blocks" :key="block.id || index" class="rounded-lg border border-line bg-surface p-3">
            <div class="flex flex-wrap items-center gap-2">
                <strong class="mr-auto text-sm">{{ labels[block.type] || block.type.replaceAll('_', ' ') }}</strong>
                <button type="button" class="text-xs text-muted hover:text-ink disabled:opacity-40" :disabled="index === 0" :aria-label="`Move ${block.type} up`" @click="move(index, -1)">↑ Up</button>
                <button type="button" class="text-xs text-muted hover:text-ink disabled:opacity-40" :disabled="index === blocks.length - 1" :aria-label="`Move ${block.type} down`" @click="move(index, 1)">↓ Down</button>
                <button type="button" class="text-xs text-red-600" :aria-label="`Remove ${block.type}`" @click="blocks.splice(index, 1)">Remove</button>
            </div>
            <div class="mt-3 space-y-2 text-sm">
                <template v-if="block.type === 'heading'">
                    <label class="block">Heading text<input :value="block.props?.text ?? ''" class="designer-input" @input="property(block, 'text', ($event.target as HTMLInputElement).value)"></label>
                    <label class="block">Level<select :value="block.props?.level ?? 1" class="designer-input" @change="property(block, 'level', Number(($event.target as HTMLSelectElement).value))"><option v-for="n in 4" :key="n" :value="n">{{ n }}</option></select></label>
                </template>
                <template v-else-if="block.type === 'text' || block.type === 'html'">
                    <label class="block">{{ block.type === 'text' ? 'Text / rich HTML' : 'HTML' }}<textarea :value="block.props?.html ?? ''" rows="4" class="designer-input font-mono" @input="property(block, 'html', ($event.target as HTMLTextAreaElement).value)" /></label>
                    <p class="text-xs text-muted">HTML and template variables are supported. Output is sanitized.</p>
                </template>
                <template v-else-if="block.type === 'image'">
                    <label class="block">Image data URL or a single template expression<input :value="block.props?.src ?? ''" class="designer-input" placeholder="&#123;&#123; organization.logo_url &#125;&#125;" @input="property(block, 'src', ($event.target as HTMLInputElement).value)"></label>
                    <label class="block">Alternative text<input :value="block.props?.alt ?? ''" class="designer-input" @input="property(block, 'alt', ($event.target as HTMLInputElement).value)"></label>
                    <label class="block">Width (%, px or mm)<input :value="block.props?.width ?? ''" class="designer-input" @input="property(block, 'width', ($event.target as HTMLInputElement).value)"></label>
                </template>
                <label v-else-if="block.type === 'logo' || block.type === 'spacer'" class="block">Height (px)<input type="number" min="0" :value="block.props?.height ?? (block.type === 'logo' ? 64 : 16)" class="designer-input" @input="property(block, 'height', Number(($event.target as HTMLInputElement).value))"></label>
                <template v-else-if="['fields', 'items_table', 'totals', 'signature'].includes(block.type)">
                    <label v-if="block.type === 'fields'" class="block">Columns<input type="number" min="1" max="4" :value="block.props?.columns ?? 2" class="designer-input" @input="property(block, 'columns', Number(($event.target as HTMLInputElement).value))"></label>
                    <template v-if="block.type === 'items_table'">
                        <label class="block">Item source expression<input :value="block.props?.source ?? 'items'" class="designer-input" @input="property(block, 'source', ($event.target as HTMLInputElement).value)"></label>
                        <label class="block"><input type="checkbox" :checked="!!block.props?.group_by_section" @change="property(block, 'group_by_section', ($event.target as HTMLInputElement).checked)"> Group by section</label>
                        <label class="block"><input type="checkbox" :checked="!!block.props?.indent_accessories" @change="property(block, 'indent_accessories', ($event.target as HTMLInputElement).checked)"> Indent accessories</label>
                        <p v-if="!block.props?.columns" class="text-xs text-muted">The default table columns are used until you add a column.</p>
                    </template>
                    <label v-if="block.type === 'signature'" class="block"><input type="checkbox" :checked="!!block.props?.show_date" @change="property(block, 'show_date', ($event.target as HTMLInputElement).checked)"> Show date</label>
                    <label v-if="block.type === 'signature' && !block.props?.lines" class="block">Signature label<input :value="block.props?.label ?? 'Signature'" class="designer-input" @input="property(block, 'label', ($event.target as HTMLInputElement).value)"></label>
                    <div v-for="(row, rowIndex) in (block.props?.[block.type === 'fields' ? 'fields' : block.type === 'items_table' ? 'columns' : block.type === 'totals' ? 'rows' : 'lines'] || [])" :key="rowIndex" class="space-y-2 rounded border border-line p-2">
                        <label class="block">Label<input v-model="row.label" class="designer-input"></label>
                        <template v-if="block.type !== 'signature'">
                            <label class="block">Value expression<input v-model="row.value" class="designer-input"></label>
                            <label class="block">Format<select v-model="row.format" class="designer-input"><option v-for="format in formats" :key="format" :value="format">{{ format || 'Default' }}</option></select></label>
                        </template>
                        <template v-if="block.type === 'items_table'">
                            <label class="block">Alignment<select v-model="row.align" class="designer-input"><option value="left">Left</option><option value="center">Center</option><option value="right">Right</option></select></label>
                            <label class="block">Width<input v-model="row.width" class="designer-input" placeholder="e.g. 20%"></label>
                        </template>
                        <label v-if="block.type === 'totals'" class="block"><input v-model="row.emphasis" type="checkbox"> Emphasize</label>
                        <button type="button" class="text-xs text-red-600" @click="block.props![block.type === 'fields' ? 'fields' : block.type === 'items_table' ? 'columns' : block.type === 'totals' ? 'rows' : 'lines'].splice(rowIndex, 1)">Remove row</button>
                    </div>
                    <button type="button" class="text-xs font-medium text-accent" @click="addRow(block, block.type === 'fields' ? 'fields' : block.type === 'items_table' ? 'columns' : block.type === 'totals' ? 'rows' : 'lines', block.type === 'signature' ? { label: '' } : { label: '', value: '' })">+ Add {{ block.type === 'items_table' ? 'column' : 'row' }}</button>
                </template>
                <template v-else-if="block.type === 'columns'">
                    <div v-for="(column, columnIndex) in (block.props?.columns || [])" :key="columnIndex" class="space-y-2 rounded border border-line p-2">
                        <label class="block">Column {{ Number(columnIndex) + 1 }} width (%)<input :value="parseFloat(column.width) || 50" type="number" min="5" max="100" class="designer-input" @input="column.width = `${($event.target as HTMLInputElement).value}%`"></label>
                        <BlockEditor v-if="column.blocks && (depth ?? 0) < 3" :blocks="column.blocks" :depth="(depth ?? 0) + 1" />
                        <button type="button" class="text-xs text-red-600" @click="block.props!.columns.splice(columnIndex, 1)">Remove column</button>
                    </div>
                    <button v-if="(block.props?.columns?.length || 0) < 4" type="button" class="text-xs font-medium text-accent" @click="addRow(block, 'columns', { width: '50%', blocks: [] })">+ Add column</button>
                </template>
                <template v-else-if="block.type === 'conditional'">
                    <label class="block">Show when expression<input :value="block.props?.condition ?? 'true'" class="designer-input" @input="property(block, 'condition', ($event.target as HTMLInputElement).value)"></label>
                    <BlockEditor v-if="block.props?.blocks && (depth ?? 0) < 3" :blocks="block.props.blocks" :depth="(depth ?? 0) + 1" />
                </template>
                <p v-else-if="!types.includes(block.type)" class="text-xs text-muted">This block is not editable here. It remains unchanged; the server may reject unknown block types.</p>
                <details v-if="!['page_break', 'html'].includes(block.type)">
                    <summary class="cursor-pointer text-xs text-muted">Appearance</summary>
                    <div class="mt-2 grid grid-cols-2 gap-2">
                        <label>Alignment<select :value="block.style?.align ?? ''" class="designer-input" @change="styling(block, 'align', ($event.target as HTMLSelectElement).value)"><option value="">Default</option><option value="left">Left</option><option value="center">Center</option><option value="right">Right</option><option value="justify">Justify</option></select></label>
                        <label>Font size (pt)<input type="number" min="1" :value="block.style?.fontSize ?? ''" class="designer-input" @input="styling(block, 'fontSize', ($event.target as HTMLInputElement).value)"></label>
                        <label>Text color<input type="color" :value="block.style?.color ?? '#18181b'" class="designer-input h-9" @input="styling(block, 'color', ($event.target as HTMLInputElement).value)"></label>
                        <label>Background color<input type="color" :value="block.style?.background ?? '#ffffff'" class="designer-input h-9" @input="styling(block, 'background', ($event.target as HTMLInputElement).value)"></label>
                        <button type="button" class="text-left text-xs text-muted" @click="styling(block, 'color', null)">Reset text color</button>
                        <button type="button" class="text-left text-xs text-muted" @click="styling(block, 'background', null)">Reset background</button>
                        <label><input type="checkbox" :checked="!!block.style?.bold" @change="styling(block, 'bold', ($event.target as HTMLInputElement).checked)"> Bold</label>
                        <label><input type="checkbox" :checked="!!block.style?.italic" @change="styling(block, 'italic', ($event.target as HTMLInputElement).checked)"> Italic</label>
                    </div>
                </details>
            </div>
        </div>
        <div class="flex gap-2">
            <select v-model="newType" aria-label="Block type to add" class="designer-input">
                <option v-for="type in types" :key="type" :value="type" :disabled="(depth ?? 0) >= 3 && ['columns', 'conditional'].includes(type)">{{ labels[type] || type }}</option>
            </select>
            <button type="button" class="whitespace-nowrap rounded border border-line px-3 text-sm" @click="add">+ Add block</button>
        </div>
    </div>
</template>
