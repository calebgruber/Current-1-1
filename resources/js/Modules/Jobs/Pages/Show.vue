<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Combobox from '@/Components/ui/Combobox.vue';
import { useFormat } from '@/composables/useFormat';

type Job = { id: number; number: string; name: string; stage_label: string; description: string | null; starts_at: string | null; returns_at: string | null; allows_allocation_edits: boolean; allowed_transitions: { value: string; label: string; allowed: boolean }[] };
type Item = { id: number; product: { name: string; sku: string | null } | null; required_quantity: number; spare_quantity: number; picked_quantity: number; checked_out_quantity: number; returned_quantity: number };
const props = defineProps<{ job: Job; items: Item[]; can: { edit: boolean; delete: boolean; items_create: boolean; items_delete: boolean; override: boolean }; findings: { message: string }[] }>();
const { datetime } = useFormat();
const item = useForm({ product_id: null as number | string | null, required_quantity: 1, spare_quantity: 0, with_accessories: true, override: false, override_reason: '' });
const stage = useForm({ stage: '', notes: '', override: false, override_reason: '' });
const addItem = () => item.post(route('jobs.items.store', props.job.id), { preserveScroll: true, onSuccess: () => item.reset() });
const removeItem = (id: number) => { if (confirm('Remove this allocation?')) router.delete(route('jobs.items.destroy', [props.job.id, id]), { preserveScroll: true }); };
const removeJob = () => { if (confirm('Delete this job?')) router.delete(route('jobs.destroy', props.job.id)); };
</script>

<template>
    <PageHeader :title="`${job.number} · ${job.name}`" :description="job.stage_label">
        <template #actions><Link class="st-link" :href="route('jobs.index')">All jobs</Link><Link v-if="can.edit" class="st-link ml-3" :href="route('jobs.edit', job.id)">Edit</Link><button v-if="can.delete" class="st-link ml-3" @click="removeJob">Delete</button></template>
    </PageHeader>
    <div class="space-y-5">
        <section class="st-card space-y-2 p-4"><p>{{ datetime(job.starts_at) }} → {{ datetime(job.returns_at) }}</p><p v-if="job.description">{{ job.description }}</p><a class="st-link" :href="route('jobs.pull-sheet', job.id)">Pull sheet</a></section>
        <section v-if="job.allowed_transitions.some(t => t.allowed)" class="st-card space-y-2 p-4">
            <h2 class="font-semibold">Move stage</h2><form class="flex flex-wrap gap-2" @submit.prevent="stage.post(route('jobs.transition', job.id), { preserveScroll: true })"><select v-model="stage.stage" required class="st-input max-w-48"><option value="">Choose stage</option><option v-for="t in job.allowed_transitions.filter(t => t.allowed)" :key="t.value" :value="t.value">{{ t.label }}</option></select><input v-model="stage.notes" class="st-input max-w-64" placeholder="Notes (optional)"><button class="st-btn" :disabled="stage.processing">Move</button></form>
            <div v-if="can.override"><label><input v-model="stage.override" type="checkbox"> Override overbooking</label><input v-if="stage.override" v-model="stage.override_reason" class="st-input" placeholder="Override reason"></div>
            <p v-for="(error, key) in stage.errors" :key="key" role="alert" class="text-red-600">{{ error }}</p>
        </section>
        <section class="st-card overflow-x-auto p-4"><h2 class="mb-2 font-semibold">Equipment</h2><p v-if="!items.length">No equipment allocated.</p><table v-else class="w-full text-left text-sm"><thead><tr><th>Product</th><th>Required + spare</th><th>Picked / out / returned</th><th></th></tr></thead><tbody><tr v-for="line in items" :key="line.id" class="border-t border-line"><td class="py-2">{{ line.product?.name ?? 'Unknown' }}</td><td>{{ line.required_quantity }} + {{ line.spare_quantity }}</td><td>{{ line.picked_quantity }} / {{ line.checked_out_quantity }} / {{ line.returned_quantity }}</td><td><button v-if="can.items_delete && job.allows_allocation_edits" class="st-link" @click="removeItem(line.id)">Remove</button></td></tr></tbody></table></section>
        <form v-if="can.items_create && job.allows_allocation_edits" class="st-card space-y-3 p-4" @submit.prevent="addItem"><h2 class="font-semibold">Add equipment</h2><Combobox v-model="item.product_id" :endpoint="route('jobs.lookup', 'products')" placeholder="Choose product" /><div class="flex gap-3"><label>Required <input v-model.number="item.required_quantity" type="number" min="0" required class="st-input"></label><label>Spare <input v-model.number="item.spare_quantity" type="number" min="0" class="st-input"></label></div><label><input v-model="item.with_accessories" type="checkbox"> Include accessories</label><div v-if="can.override"><label><input v-model="item.override" type="checkbox"> Override overbooking</label><input v-if="item.override" v-model="item.override_reason" class="st-input" placeholder="Override reason"></div><p v-for="(error, key) in item.errors" :key="key" role="alert" class="text-red-600">{{ error }}</p><button class="st-btn" :disabled="item.processing">Add</button></form>
        <section v-if="findings.length" class="st-card p-4"><h2 class="font-semibold">Validation</h2><p v-for="(finding, i) in findings" :key="i">{{ finding.message }}</p></section>
    </div>
</template>
<style scoped>.st-btn { border: 1px solid var(--st-line); border-radius: .375rem; padding: .375rem .75rem; background: var(--st-surface); } .st-btn:hover { background: var(--st-hover); } .st-btn:disabled { opacity: .5; }</style>
