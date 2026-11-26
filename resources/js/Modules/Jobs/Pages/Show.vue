<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Badge from '@/Components/ui/Badge.vue';
import { jobStageTone } from '@/lib/status';
import Combobox from '@/Components/ui/Combobox.vue';
import CustomFieldDisplay from '@/Components/custom-fields/CustomFieldDisplay.vue';
import { useFormat } from '@/composables/useFormat';
import { usePermissions } from '@/composables/usePermissions';
import { SlidersHorizontal } from 'lucide-vue-next';
import type { CustomFieldSchema } from '@/types';

type Job = { id: number; number: string; name: string; stage: string; stage_label: string; description: string | null; starts_at: string | null; returns_at: string | null; allows_allocation_edits: boolean; allowed_transitions: { value: string; label: string; allowed: boolean }[] };
type Item = { id: number; product: { name: string; sku: string | null } | null; required_quantity: number; spare_quantity: number; picked_quantity: number; checked_out_quantity: number; returned_quantity: number };
const props = defineProps<{ job: Job; items: Item[]; can: { edit: boolean; delete: boolean; items_create: boolean; items_edit: boolean; items_delete: boolean; override: boolean }; findings: { message: string }[]; customFieldSchema: CustomFieldSchema[]; customFields: Record<string, any> }>();
const { datetime } = useFormat();
const { can: hasPermission } = usePermissions();
const item = useForm({ product_id: null as number | string | null, required_quantity: 1, spare_quantity: 0, with_accessories: true, override: false, override_reason: '' });
const stage = useForm({ stage: '', notes: '', override: false, override_reason: '' });
const editing = ref<Item | null>(null);
const allocation = useForm({ required_quantity: 0, spare_quantity: 0, override: false, override_reason: '' });
const editItem = (line: Item) => {
    allocation.clearErrors();
    allocation.defaults({ required_quantity: line.required_quantity, spare_quantity: line.spare_quantity, override: false, override_reason: '' });
    allocation.reset();
    editing.value = line;
};
const saveItem = () => {
    if (!editing.value) return;
    allocation.put(route('jobs.items.update', [props.job.id, editing.value.id]), {
        preserveScroll: true,
        onSuccess: () => { editing.value = null; },
    });
};
const addItem = () => item.post(route('jobs.items.store', props.job.id), { preserveScroll: true, onSuccess: () => item.reset() });
const removeItem = (id: number) => { if (confirm('Remove this allocation?')) router.delete(route('jobs.items.destroy', [props.job.id, id]), { preserveScroll: true }); };
const removeJob = () => { if (confirm('Delete this job?')) router.delete(route('jobs.destroy', props.job.id)); };
</script>

<template>
    <PageHeader :title="`${job.number} · ${job.name}`" :description="job.stage_label">
        <template #description><Badge :tone="jobStageTone[job.stage]">{{ job.stage_label }}</Badge></template>
        <template #actions><Link class="st-link" :href="route('jobs.index')">All jobs</Link><Link v-if="can.edit" class="st-link ml-3" :href="route('jobs.edit', job.id)">Edit</Link><button v-if="can.delete" class="st-link ml-3" @click="removeJob">Delete</button></template>
    </PageHeader>
    <div class="space-y-5">
        <section class="st-card space-y-2 p-4"><p>{{ datetime(job.starts_at) }} → {{ datetime(job.returns_at) }}</p><p v-if="job.description">{{ job.description }}</p><a class="st-link" :href="route('jobs.pull-sheet', job.id)">Pull sheet</a></section>
        <section v-if="customFieldSchema.length || (hasPermission('settings.custom_fields.view') && route().has('settings.custom-fields.index'))" class="st-card space-y-3 p-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="font-semibold">Custom fields</h2>
                <Link v-if="hasPermission('settings.custom_fields.view') && route().has('settings.custom-fields.index')" class="st-link inline-flex items-center gap-1" :href="route('settings.custom-fields.index')"><SlidersHorizontal class="size-4" aria-hidden="true" /> Manage fields</Link>
            </div>
            <CustomFieldDisplay v-if="customFieldSchema.length" :schema="customFieldSchema" :values="customFields" />
            <p v-else class="text-sm text-muted">No custom fields configured for jobs.</p>
        </section>
        <section v-if="job.allowed_transitions.some(t => t.allowed)" class="st-card space-y-2 p-4">
            <h2 class="font-semibold">Move stage</h2><form class="flex flex-wrap gap-2" @submit.prevent="stage.post(route('jobs.transition', job.id), { preserveScroll: true })"><select v-model="stage.stage" required class="st-input max-w-48"><option value="">Choose stage</option><option v-for="t in job.allowed_transitions.filter(t => t.allowed)" :key="t.value" :value="t.value">{{ t.label }}</option></select><input v-model="stage.notes" class="st-input max-w-64" placeholder="Notes (optional)"><button class="st-btn" :disabled="stage.processing">Move</button></form>
            <div v-if="can.override"><label><input v-model="stage.override" type="checkbox"> Override overbooking</label><input v-if="stage.override" v-model="stage.override_reason" class="st-input" placeholder="Override reason"></div>
            <p v-for="(error, key) in stage.errors" :key="key" role="alert" class="text-red-600">{{ error }}</p>
        </section>
        <section class="st-card overflow-x-auto p-4"><h2 class="mb-2 font-semibold">Equipment</h2><p v-if="!items.length">No equipment allocated.</p><table v-else class="w-full text-left text-sm"><thead><tr><th>Product</th><th>Required + spare</th><th>Picked / out / returned</th><th></th></tr></thead><tbody><tr v-for="line in items" :key="line.id" class="border-t border-line"><td class="py-2">{{ line.product?.name ?? 'Unknown' }}</td><td>{{ line.required_quantity }} + {{ line.spare_quantity }}</td><td>{{ line.picked_quantity }} / {{ line.checked_out_quantity }} / {{ line.returned_quantity }}</td><td><button v-if="can.items_edit && job.allows_allocation_edits" class="st-link mr-3" :disabled="allocation.processing" :aria-label="`Edit allocation for ${line.product?.name ?? 'Unknown'}`" @click="editItem(line)">Edit</button><button v-if="can.items_delete && job.allows_allocation_edits" class="st-link" @click="removeItem(line.id)">Remove</button></td></tr></tbody></table></section>
        <form v-if="editing && can.items_edit && job.allows_allocation_edits" class="st-card space-y-3 p-4" @submit.prevent="saveItem">
            <h2 class="font-semibold">Edit allocation: {{ editing.product?.name ?? 'Unknown' }}</h2>
            <div class="flex flex-wrap gap-3"><label>Required <input v-model.number="allocation.required_quantity" type="number" min="0" max="100000" required class="st-input"></label><label>Spare <input v-model.number="allocation.spare_quantity" type="number" min="0" max="100000" required class="st-input"></label></div>
            <div v-if="can.override"><label><input v-model="allocation.override" type="checkbox"> Override overbooking</label><input v-if="allocation.override" v-model="allocation.override_reason" class="st-input" placeholder="Override reason" aria-label="Override reason" required minlength="5" maxlength="500"></div>
            <p v-for="(error, key) in allocation.errors" :key="key" role="alert" class="text-red-600">{{ error }}</p>
            <div class="flex gap-2"><button class="st-btn" :disabled="allocation.processing">Save allocation</button><button type="button" class="st-btn" :disabled="allocation.processing" @click="editing = null">Cancel</button></div>
        </form>
        <form v-if="can.items_create && job.allows_allocation_edits" class="st-card space-y-3 p-4" @submit.prevent="addItem"><h2 class="font-semibold">Add equipment</h2><Combobox v-model="item.product_id" :endpoint="route('jobs.lookup', 'products')" placeholder="Choose product" /><div class="flex gap-3"><label>Required <input v-model.number="item.required_quantity" type="number" min="0" required class="st-input"></label><label>Spare <input v-model.number="item.spare_quantity" type="number" min="0" class="st-input"></label></div><label><input v-model="item.with_accessories" type="checkbox"> Include accessories</label><div v-if="can.override"><label><input v-model="item.override" type="checkbox"> Override overbooking</label><input v-if="item.override" v-model="item.override_reason" class="st-input" placeholder="Override reason"></div><p v-for="(error, key) in item.errors" :key="key" role="alert" class="text-red-600">{{ error }}</p><button class="st-btn" :disabled="item.processing">Add</button></form>
        <section v-if="findings.length" class="st-card p-4"><h2 class="font-semibold">Validation</h2><p v-for="(finding, i) in findings" :key="i">{{ finding.message }}</p></section>
    </div>
</template>
<style scoped>.st-btn { border: 1px solid var(--st-line); border-radius: .375rem; padding: .375rem .75rem; background: var(--st-surface); } .st-btn:hover { background: var(--st-hover); } .st-btn:disabled { opacity: .5; }</style>
