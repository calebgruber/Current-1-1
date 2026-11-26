<script setup lang="ts">
/** Read-only definition list of custom field values: <CustomFieldDisplay :schema="customFieldSchema" :values="customFields" /> */
import { useFormat } from '@/composables/useFormat';
import { usePage } from '@inertiajs/vue3';
import { customFieldValue, formatCustomDecimal } from '@/lib/customFieldValue';
import type { CustomFieldSchema, SharedProps } from '@/types';

const props = defineProps<{ schema: CustomFieldSchema[]; values: Record<string, any> }>();
const { date, datetime } = useFormat();
const page = usePage<SharedProps>();
const valueOf = (key: string) => customFieldValue(props.values, key);

const labelFor = (f: CustomFieldSchema, v: string) => {
    const o = f.options.find((x) => (typeof x === 'string' ? x : x.value) === v);
    return o === undefined ? v : typeof o === 'string' ? o : o.label;
};

const display = (f: CustomFieldSchema): string => {
    const v = valueOf(f.key);
    if (v === null || v === undefined || v === '' || (Array.isArray(v) && !v.length)) return '—';
    switch (f.type) {
        case 'currency': return formatCustomDecimal(v, page.props.app?.currency ?? 'USD');
        case 'number': return formatCustomDecimal(v);
        case 'date': return date(v);
        case 'datetime': return datetime(v);
        case 'checkbox': return v ? 'Yes' : 'No';
        case 'dropdown': return labelFor(f, v);
        case 'multi_select': return (v as string[]).map((x) => labelFor(f, x)).join(', ');
        case 'relation': return `${v.type} #${v.id}`;
        case 'user':
        case 'asset': return `#${v}`;
        case 'file': return v.name ?? 'File';
        default: return String(v);
    }
};
const isSafeUrl = (v: unknown) => typeof v === 'string' && /^https?:\/\//i.test(v);
</script>

<template>
    <dl v-if="schema.length" class="grid gap-x-6 gap-y-3 sm:grid-cols-2">
        <div v-for="f in schema" :key="f.key" :class="f.type === 'long_text' ? 'sm:col-span-2' : ''">
            <dt class="text-xs text-muted">{{ f.label }}</dt>
            <dd class="mt-0.5 text-sm break-words whitespace-pre-line">
                <a v-if="f.type === 'url' && isSafeUrl(valueOf(f.key))" :href="valueOf(f.key)" target="_blank" rel="noopener noreferrer" class="st-link">{{ valueOf(f.key) }}</a>
                <a v-else-if="f.type === 'file' && valueOf(f.key)?.path" :href="route('custom-fields.download', { path: valueOf(f.key).path })" class="st-link">{{ display(f) }}</a>
                <template v-else>{{ display(f) }}</template>
            </dd>
        </div>
    </dl>
</template>
