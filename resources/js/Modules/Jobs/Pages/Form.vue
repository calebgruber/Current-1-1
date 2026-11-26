<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Combobox from '@/Components/ui/Combobox.vue';
import CustomFieldInputs from '@/Components/custom-fields/CustomFieldInputs.vue';
import { useFormat } from '@/composables/useFormat';
import { localDateTimeToUtc } from '@/lib/localDateTime';
import type { CustomFieldSchema } from '@/types';

type Job = { id: number; name: string; description: string | null; workflow_status: string | null; color: string | null; client_contact_id: number | null; venue_location_id: number | null; owner_id: number | null; client_label: string | null; venue_label: string | null; owner_label: string | null; prep_starts_at: string | null; starts_at: string | null; ends_at: string | null; returns_at: string | null };
const props = defineProps<{ job: Job | null; contacts: { contact_id: number; role: string | null }[]; customFieldSchema: CustomFieldSchema[]; customFields: Record<string, any>; confirmedHold: boolean }>();
const { toLocalInput } = useFormat();
const initialDates = {
    prep_starts_at: toLocalInput(props.job?.prep_starts_at),
    starts_at: toLocalInput(props.job?.starts_at),
    ends_at: toLocalInput(props.job?.ends_at),
    returns_at: toLocalInput(props.job?.returns_at),
};
const form = useForm({
    name: props.job?.name ?? '', description: props.job?.description ?? '', workflow_status: props.job?.workflow_status ?? '', color: props.job?.color ?? '',
    client_contact_id: props.job?.client_contact_id ?? null as number | string | null, venue_location_id: props.job?.venue_location_id ?? null as number | string | null, owner_id: props.job?.owner_id ?? null as number | string | null,
    ...initialDates,
    contacts: props.contacts, custom_fields: { ...props.customFields }, override: false, override_reason: '',
});
form.transform((data) => ({
    ...data,
    prep_starts_at: localDateTimeToUtc(data.prep_starts_at, initialDates.prep_starts_at, props.job?.prep_starts_at),
    starts_at: localDateTimeToUtc(data.starts_at, initialDates.starts_at, props.job?.starts_at),
    ends_at: localDateTimeToUtc(data.ends_at, initialDates.ends_at, props.job?.ends_at),
    returns_at: localDateTimeToUtc(data.returns_at, initialDates.returns_at, props.job?.returns_at),
}));
const submit = () => props.job ? form.put(route('jobs.update', props.job.id)) : form.post(route('jobs.store'));
</script>

<template>
    <PageHeader :title="job ? 'Edit job' : 'New job'"><template #actions><Link class="st-link" :href="job ? route('jobs.show', job.id) : route('jobs.index')">Cancel</Link></template></PageHeader>
    <form class="st-card max-w-3xl space-y-4 p-5" @submit.prevent="submit">
        <div v-if="Object.keys(form.errors).length" role="alert" class="text-red-600"><p v-for="(error, key) in form.errors" :key="key">{{ error }}</p></div>
        <label class="block">Name <input v-model="form.name" required class="st-input"></label>
        <div class="grid gap-4 sm:grid-cols-2">
            <label>Client <Combobox v-model="form.client_contact_id" :endpoint="route('jobs.lookup', 'contacts')" :initial-label="job?.client_label" /></label>
            <label>Venue <Combobox v-model="form.venue_location_id" :endpoint="route('jobs.lookup', 'locations')" :initial-label="job?.venue_label" /></label>
            <label>Owner <Combobox v-model="form.owner_id" :endpoint="route('jobs.lookup', 'users')" :initial-label="job?.owner_label" /></label>
            <label>Workflow status <input v-model="form.workflow_status" class="st-input"></label>
            <label>Prep start <input v-model="form.prep_starts_at" type="datetime-local" class="st-input"></label>
            <label>Event start <input v-model="form.starts_at" type="datetime-local" class="st-input"></label>
            <label>Event end <input v-model="form.ends_at" type="datetime-local" class="st-input"></label>
            <label>Return <input v-model="form.returns_at" type="datetime-local" class="st-input"></label>
        </div>
        <label class="block">Description <textarea v-model="form.description" class="st-input" rows="4" /></label>
        <CustomFieldInputs v-model="form.custom_fields" :schema="customFieldSchema" :errors="form.errors" />
        <div v-if="confirmedHold && $page.props.auth && job">
            <label><input v-model="form.override" type="checkbox"> Override overbooking (requires permission)</label>
            <label v-if="form.override" class="block">Reason <input v-model="form.override_reason" class="st-input" required minlength="5"></label>
        </div>
        <button class="st-btn" :disabled="form.processing" type="submit">Save job</button>
    </form>
</template>
<style scoped>.st-btn { border: 1px solid var(--st-line); border-radius: .375rem; padding: .375rem .75rem; background: var(--st-surface); } .st-btn:hover { background: var(--st-hover); } .st-btn:disabled { opacity: .5; }</style>
