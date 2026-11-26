<script setup lang="ts">
/**
 * Renders inputs for administrator-defined custom fields.
 *   <CustomFieldInputs v-model="form.custom_fields" :schema="customFieldSchema" :errors="form.errors" />
 * Errors are read from keys "custom_fields.{key}" (as thrown by CustomFieldService::validate).
 */
import { computed, ref } from 'vue';
import { Paperclip, X } from 'lucide-vue-next';
import FormField from '@/Components/ui/FormField.vue';
import TextInput from '@/Components/ui/TextInput.vue';
import TextareaInput from '@/Components/ui/Textarea.vue';
import SelectInput from '@/Components/ui/SelectInput.vue';
import Checkbox from '@/Components/ui/Checkbox.vue';
import Combobox from '@/Components/ui/Combobox.vue';
import { http, errorMessage } from '@/lib/http';
import { useToast } from '@/composables/useToast';
import { useFormat } from '@/composables/useFormat';
import type { CustomFieldSchema } from '@/types';

const model = defineModel<Record<string, any>>({ required: true });
const props = withDefaults(defineProps<{ schema: CustomFieldSchema[]; errors?: Record<string, string>; columns?: 1 | 2 }>(), { columns: 2 });

const toast = useToast();
const { toLocalInput } = useFormat();
const uploading = ref<string | null>(null);
const urlPlaceholder = 'https:' + '//';

const groups = computed(() => {
    const map = new Map<string, CustomFieldSchema[]>();
    for (const field of props.schema) {
        const g = field.group || '';
        map.set(g, [...(map.get(g) ?? []), field]);
    }
    return [...map.entries()];
});

const optionsOf = (f: CustomFieldSchema) => f.options.map((o) => (typeof o === 'string' ? { value: o, label: o } : o));
const errorOf = (f: CustomFieldSchema) => props.errors?.[`custom_fields.${f.key}`] ?? null;
const set = (key: string, value: unknown) => (model.value = { ...model.value, [key]: value });
const relationTypes = (f: CustomFieldSchema) => {
    const allowed = (f.validation.allowed as string[] | undefined) ?? (f.validation.entity ? [f.validation.entity as string] : []);
    return allowed.length ? allowed : ['product', 'asset', 'job', 'contact', 'location'];
};

const toggleMulti = (f: CustomFieldSchema, value: string, on: boolean) => {
    const current: string[] = Array.isArray(model.value[f.key]) ? [...model.value[f.key]] : [];
    set(f.key, on ? [...new Set([...current, value])] : current.filter((v) => v !== value));
};

const upload = async (f: CustomFieldSchema, event: Event) => {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (!file) return;
    uploading.value = f.key;
    try {
        const body = new FormData();
        body.append('file', file);
        const { data } = await http.post(route('custom-fields.upload'), body);
        set(f.key, data);
    } catch (e) {
        toast.error(errorMessage(e, 'Upload failed.'));
    } finally {
        uploading.value = null;
    }
};

const relationValue = (f: CustomFieldSchema) => (model.value[f.key] as { type: string; id: number } | null) ?? null;
const setRelation = (f: CustomFieldSchema, patch: Partial<{ type: string; id: number | null }>) => {
    const current = relationValue(f) ?? { type: relationTypes(f)[0], id: null };
    const next = { ...current, ...patch };
    set(f.key, next.id ? next : null);
};
</script>

<template>
    <div v-if="schema.length" class="space-y-6">
        <fieldset v-for="[group, fields] in groups" :key="group">
            <legend v-if="group" class="mb-3 text-xs font-semibold tracking-wide text-muted uppercase">{{ group }}</legend>
            <div :class="['grid gap-4', columns === 2 ? 'sm:grid-cols-2' : '']">
                <template v-for="f in fields" :key="f.key">
                <FormField
                    :label="f.type === 'checkbox' ? undefined : f.label"
                    :for="`cf-${f.key}`"
                    :error="errorOf(f)"
                    :hint="f.help_text ?? undefined"
                    :required="f.required"
                    :class="f.type === 'long_text' || f.type === 'multi_select' ? 'sm:col-span-2' : ''"
                >
                    <TextInput v-if="f.type === 'text'" :id="`cf-${f.key}`" :model-value="model[f.key] ?? ''" :invalid="!!errorOf(f)" @update:model-value="set(f.key, $event)" />
                    <TextInput v-else-if="f.type === 'url'" :id="`cf-${f.key}`" type="url" :placeholder="urlPlaceholder" :model-value="model[f.key] ?? ''" :invalid="!!errorOf(f)" @update:model-value="set(f.key, $event)" />
                    <TextareaInput v-else-if="f.type === 'long_text'" :id="`cf-${f.key}`" :rows="3" :model-value="model[f.key] ?? ''" :invalid="!!errorOf(f)" @update:model-value="set(f.key, $event)" />
                    <TextInput v-else-if="f.type === 'number'" :id="`cf-${f.key}`" type="number" step="any" :model-value="model[f.key] ?? ''" :invalid="!!errorOf(f)" @update:model-value="set(f.key, $event)" />
                    <div v-else-if="f.type === 'currency'" class="relative">
                        <span class="pointer-events-none absolute top-1/2 left-2.5 -translate-y-1/2 text-faint">$</span>
                        <TextInput :id="`cf-${f.key}`" type="number" step="0.01" class="pl-6" :model-value="model[f.key] ?? ''" :invalid="!!errorOf(f)" @update:model-value="set(f.key, $event)" />
                    </div>
                    <TextInput v-else-if="f.type === 'date'" :id="`cf-${f.key}`" type="date" :model-value="model[f.key] ?? ''" :invalid="!!errorOf(f)" @update:model-value="set(f.key, $event)" />
                    <TextInput v-else-if="f.type === 'datetime'" :id="`cf-${f.key}`" type="datetime-local" :model-value="toLocalInput(model[f.key])" :invalid="!!errorOf(f)" @update:model-value="set(f.key, $event)" />
                    <Checkbox v-else-if="f.type === 'checkbox'" :model-value="!!model[f.key]" :label="f.label" @update:model-value="set(f.key, $event)" />
                    <SelectInput v-else-if="f.type === 'dropdown'" :id="`cf-${f.key}`" :options="optionsOf(f)" placeholder="—" :model-value="model[f.key] ?? null" :invalid="!!errorOf(f)" @update:model-value="set(f.key, $event)" />
                    <div v-else-if="f.type === 'multi_select'" class="flex flex-wrap gap-x-4 gap-y-2">
                        <Checkbox
                            v-for="o in optionsOf(f)"
                            :key="o.value"
                            :label="o.label"
                            :model-value="Array.isArray(model[f.key]) && model[f.key].includes(o.value)"
                            @update:model-value="toggleMulti(f, o.value, !!$event)"
                        />
                    </div>
                    <Combobox
                        v-else-if="f.type === 'user' || f.type === 'asset'"
                        :endpoint="route('custom-fields.lookup', { type: f.type })"
                        :model-value="model[f.key] ?? null"
                        :initial-label="model[`${f.key}__label`] ?? (model[f.key] ? `#${model[f.key]}` : null)"
                        :invalid="!!errorOf(f)"
                        @update:model-value="set(f.key, $event)"
                    />
                    <div v-else-if="f.type === 'relation'" class="flex gap-2">
                        <SelectInput
                            class="w-36 shrink-0"
                            :options="relationTypes(f)"
                            :model-value="relationValue(f)?.type ?? relationTypes(f)[0]"
                            @update:model-value="setRelation(f, { type: String($event), id: null })"
                        />
                        <Combobox
                            class="flex-1"
                            :endpoint="route('custom-fields.lookup', { type: relationValue(f)?.type ?? relationTypes(f)[0] })"
                            :model-value="relationValue(f)?.id ?? null"
                            :initial-label="relationValue(f) ? `#${relationValue(f)!.id}` : null"
                            :invalid="!!errorOf(f)"
                            @update:model-value="setRelation(f, { id: $event as number | null })"
                        />
                    </div>
                    <div v-else-if="f.type === 'file'">
                        <div v-if="model[f.key]?.path" class="flex items-center gap-2 rounded-md border border-line px-2.5 py-1.5 text-sm">
                            <Paperclip class="size-4 text-muted" />
                            <span class="flex-1 truncate">{{ model[f.key].name }}</span>
                            <button type="button" class="text-faint hover:text-ink" aria-label="Remove file" @click="set(f.key, null)"><X class="size-4" /></button>
                        </div>
                        <input v-else :id="`cf-${f.key}`" type="file" class="block w-full text-sm text-muted file:mr-3 file:rounded-md file:border file:border-line file:bg-surface file:px-3 file:py-1.5 file:text-sm" :disabled="uploading === f.key" @change="upload(f, $event)" />
                    </div>
                </FormField>
                </template>
            </div>
        </fieldset>
    </div>
</template>
