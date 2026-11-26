<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Combobox from '@/Components/ui/Combobox.vue';
import CustomFieldInputs from '@/Components/custom-fields/CustomFieldInputs.vue';
import { useFormat } from '@/composables/useFormat';
import { localDateTimeToUtc } from '@/lib/localDateTime';
import type { CustomFieldSchema } from '@/types';

type Row = { id: number; product_id: number; product: string | null; job_id: number | null; job: { number: string; name: string } | null; name: string | null; quantity: number; spare_quantity: number; starts_at: string; ends_at: string; status: string; notes: string | null };
type Defaults = { product_id: number | null; product_label: string | null; job_id: number | null; job_label: string | null; starts_at: string | null; ends_at: string | null };
const props = defineProps<{ reservation: Row | null; defaults: Defaults | null; statuses: { value: string; label: string }[]; customFieldSchema: CustomFieldSchema[]; customFields: Record<string, any>; canOverride: boolean }>();
const { toLocalInput } = useFormat();
const originalStart = props.reservation?.starts_at ?? props.defaults?.starts_at;
const originalEnd = props.reservation?.ends_at ?? props.defaults?.ends_at;
const initialStart = toLocalInput(originalStart);
const initialEnd = toLocalInput(originalEnd);
const form = useForm({ product_id: props.reservation?.product_id ?? props.defaults?.product_id ?? null as number | string | null, job_id: props.reservation?.job_id ?? props.defaults?.job_id ?? null as number | string | null, name: props.reservation?.name ?? '', quantity: props.reservation?.quantity ?? 1, spare_quantity: props.reservation?.spare_quantity ?? 0, starts_at: initialStart, ends_at: initialEnd, status: props.reservation?.status ?? 'tentative', notes: props.reservation?.notes ?? '', custom_fields: { ...props.customFields }, override: false, override_reason: '' });
form.transform((data) => ({
    ...data,
    starts_at: localDateTimeToUtc(data.starts_at, initialStart, originalStart),
    ends_at: localDateTimeToUtc(data.ends_at, initialEnd, originalEnd),
}));
const submit = () => props.reservation ? form.put(route('reservations.update', props.reservation.id)) : form.post(route('reservations.store'));
</script>
<template>
    <PageHeader :title="reservation ? 'Edit reservation' : 'New reservation'"><template #actions><Link class="st-link" :href="reservation ? route('reservations.show', reservation.id) : route('reservations.index')">Cancel</Link></template></PageHeader>
    <form class="st-card max-w-3xl space-y-4 p-5" @submit.prevent="submit">
        <div v-if="Object.keys(form.errors).length" role="alert" class="text-red-600"><p v-for="(error, key) in form.errors" :key="key">{{ error }}</p></div>
        <label class="block">Product <Combobox v-model="form.product_id" :endpoint="route('jobs.lookup', 'products')" :initial-label="reservation?.product ?? defaults?.product_label" /></label>
        <label class="block">Job (optional) <Combobox v-model="form.job_id" :endpoint="route('jobs.lookup', 'jobs')" :initial-label="reservation?.job ? `${reservation.job.number} — ${reservation.job.name}` : defaults?.job_label" /></label>
        <label class="block">Name <input v-model="form.name" class="st-input" placeholder="Required without a job"></label>
        <div class="grid gap-4 sm:grid-cols-2"><label>Quantity <input v-model.number="form.quantity" class="st-input" type="number" min="1" required></label><label>Spare <input v-model.number="form.spare_quantity" class="st-input" type="number" min="0"></label><label>Start <input v-model="form.starts_at" class="st-input" type="datetime-local" required></label><label>End <input v-model="form.ends_at" class="st-input" type="datetime-local" required></label></div>
        <label class="block">Status <select v-model="form.status" class="st-input"><option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option></select></label>
        <label class="block">Notes <textarea v-model="form.notes" class="st-input" rows="3" /></label>
        <CustomFieldInputs v-model="form.custom_fields" :schema="customFieldSchema" :errors="form.errors" />
        <div v-if="canOverride"><label><input v-model="form.override" type="checkbox"> Override overbooking</label><label v-if="form.override" class="block">Reason <input v-model="form.override_reason" class="st-input" required minlength="5"></label></div>
        <button class="st-btn" :disabled="form.processing">Save reservation</button>
    </form>
</template>
<style scoped>.st-btn { border: 1px solid var(--st-line); border-radius: .375rem; padding: .375rem .75rem; background: var(--st-surface); } .st-btn:hover { background: var(--st-hover); } .st-btn:disabled { opacity: .5; }</style>
