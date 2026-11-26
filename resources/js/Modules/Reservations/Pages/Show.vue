<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { useFormat } from '@/composables/useFormat';
type Row = { id: number; label: string; product: string | null; total: number; status: string; starts_at: string; ends_at: string; notes: string | null; job_generated: boolean; job: { id: number; number: string; name: string } | null };
const props = defineProps<{ reservation: Row; conflict: { message: string } | null; can: { edit: boolean; delete: boolean; approve: boolean; override: boolean } }>();
const { datetime } = useFormat();
const form = useForm({ override: false, override_reason: '' });
const release = () => { if (confirm('Release this reservation?')) router.post(route('reservations.release', props.reservation.id), {}, { preserveScroll: true }); };
const remove = () => { if (confirm('Delete this reservation?')) router.delete(route('reservations.destroy', props.reservation.id)); };
</script>
<template>
    <PageHeader :title="reservation.label" :description="reservation.status"><template #actions><Link class="st-link" :href="route('reservations.index')">All reservations</Link><Link v-if="can.edit" class="st-link ml-3" :href="route('reservations.edit', reservation.id)">Edit</Link><button v-if="can.delete" class="st-link ml-3" @click="remove">Delete</button></template></PageHeader>
    <section class="st-card max-w-3xl space-y-3 p-5"><p>{{ reservation.product }} · {{ reservation.total }} total</p><p>{{ datetime(reservation.starts_at) }} – {{ datetime(reservation.ends_at) }}</p><p v-if="reservation.notes">{{ reservation.notes }}</p><p v-if="reservation.job">Job: {{ reservation.job.number }} · {{ reservation.job.name }}</p><p v-if="reservation.job_generated">This reservation is managed by its job allocation.</p><p v-if="conflict?.message">{{ conflict.message }}</p>
        <form v-if="can.approve && reservation.status !== 'confirmed'" class="space-y-2" @submit.prevent="form.post(route('reservations.confirm', reservation.id), { preserveScroll: true })"><div v-if="can.override"><label><input v-model="form.override" type="checkbox"> Override overbooking</label><input v-if="form.override" v-model="form.override_reason" class="st-input" placeholder="Override reason" required minlength="5"></div><p v-for="(error, key) in form.errors" :key="key" role="alert" class="text-red-600">{{ error }}</p><button class="st-btn" :disabled="form.processing">Confirm</button></form>
        <button v-if="(can.edit || can.approve) && reservation.status !== 'released'" class="st-btn" @click="release">Release</button>
    </section>
</template>
<style scoped>.st-btn { border: 1px solid var(--st-line); border-radius: .375rem; padding: .375rem .75rem; background: var(--st-surface); } .st-btn:hover { background: var(--st-hover); } .st-btn:disabled { opacity: .5; }</style>
