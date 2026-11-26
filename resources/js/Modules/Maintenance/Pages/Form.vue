<script setup lang="ts">
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Button from '@/Components/ui/Button.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { useFormat } from '@/composables/useFormat';
import { usePermissions } from '@/composables/usePermissions';
import { localDateTimeToUtc } from '@/lib/localDateTime';

const props = defineProps<{ record: Record<string, any> | null; products: Record<string, any>[]; assets: Record<string, any>[]; users: Record<string, any>[]; jobs: Record<string, any>[]; statuses: { value: string; label: string }[]; types: string[]; priorities: string[] }>();
const { can } = usePermissions();
const { toLocalInput } = useFormat();
const r = props.record;
const initialDueAt = toLocalInput(r?.due_at);
const form = useForm({ product_id: r?.product_id ?? null, asset_id: r?.asset_id ?? null, quantity: r?.quantity ?? 1, title: r?.title ?? '', type: r?.type ?? 'repair', status: r?.status ?? 'open', priority: r?.priority ?? 'normal', description: r?.description ?? '', resolution: r?.resolution ?? '', cost: r?.cost ?? '', job_id: r?.job_id ?? null, assigned_to: r?.assigned_to ?? null, due_at: initialDueAt });
form.transform(data => ({ ...data, due_at: localDateTimeToUtc(data.due_at, initialDueAt, r?.due_at) }));
const product = computed(() => props.products.find(p => p.id === Number(form.product_id)));
const assetOptions = computed(() => props.assets.filter(a => a.product_id === Number(form.product_id) || (r && a.id === r.asset_id)));
const statuses = computed(() => props.statuses.filter(s => can('maintenance.records.approve') || !['completed', 'written_off'].includes(s.value) || s.value === r?.status));
const submit = () => r ? form.put(`/maintenance/${r.id}`) : form.post('/maintenance');
</script>
<template>
    <PageHeader :title="r ? 'Edit maintenance record' : 'New maintenance record'" />
    <form class="st-card max-w-3xl space-y-4 p-5" @submit.prevent="submit">
        <div v-if="Object.keys(form.errors).length" class="text-sm text-red-600" role="alert"><p v-for="(error, key) in form.errors" :key="key">{{ error }}</p></div>
        <div class="grid gap-4 sm:grid-cols-2">
            <label class="st-label">Product <select v-model="form.product_id" class="st-input" required :disabled="!!r" @change="form.asset_id = null"><option :value="null">Select product</option><option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }}{{ p.deleted_at ? ' (deleted)' : '' }}</option></select></label>
            <label v-if="product?.tracking === 'serialized'" class="st-label">Asset <select v-model="form.asset_id" class="st-input" required :disabled="!!r"><option :value="null">Select asset</option><option v-for="a in assetOptions" :key="a.id" :value="a.id">{{ a.asset_number }}{{ a.deleted_at ? ' (deleted)' : '' }}</option></select></label>
            <label class="st-label">Quantity <input v-model.number="form.quantity" class="st-input" type="number" min="1" :max="product?.tracking === 'serialized' ? 1 : 10000000" required :disabled="!!r"></label>
            <label class="st-label">Title <input v-model="form.title" class="st-input" maxlength="255" required></label>
            <label class="st-label">Type <select v-model="form.type" class="st-input"><option v-for="t in types" :key="t" :value="t">{{ t.replaceAll('_', ' ') }}</option></select></label>
            <label class="st-label">Status <select v-model="form.status" class="st-input"><option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option></select></label>
            <label class="st-label">Priority <select v-model="form.priority" class="st-input"><option v-for="p in priorities" :key="p" :value="p">{{ p }}</option></select></label>
            <label class="st-label">Assigned to <select v-model="form.assigned_to" class="st-input"><option :value="null">Unassigned</option><option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option></select></label>
            <label class="st-label">Job <select v-model="form.job_id" class="st-input"><option :value="null">None</option><option v-for="j in jobs" :key="j.id" :value="j.id">{{ j.number }} — {{ j.name }}{{ j.deleted_at ? ' (deleted)' : '' }}</option></select></label>
            <label class="st-label">Due date <input v-model="form.due_at" class="st-input" type="datetime-local"></label>
            <label class="st-label">Cost <input v-model="form.cost" class="st-input" type="number" min="0" step="0.01"></label>
        </div>
        <label class="st-label">Description <textarea v-model="form.description" class="st-input" rows="3" /></label>
        <label class="st-label">Resolution <textarea v-model="form.resolution" class="st-input" rows="3" /></label>
        <p v-if="product?.tracking === 'bulk'" class="text-sm text-muted">This record does not make bulk stock unavailable. Adjust stock quantities separately in inventory.</p>
        <p v-if="product?.deleted_at || assetOptions.find(a => a.id === form.asset_id)?.deleted_at" class="text-sm text-muted">Deleted items can be closed but cannot be reopened. A deleted asset's status is not changed.</p>
        <Button type="submit" variant="primary" :loading="form.processing">Save record</Button>
    </form>
</template>
