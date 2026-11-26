<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Pagination from '@/Components/ui/Pagination.vue';
import Badge from '@/Components/ui/Badge.vue';
import { reservationStatusTone } from '@/lib/status';
import { useFormat } from '@/composables/useFormat';
import { usePermissions } from '@/composables/usePermissions';
import type { Paginated } from '@/types';

type Reservation = { id: number; label: string; product: string | null; total: number; status: string; starts_at: string; ends_at: string };
const props = defineProps<{ reservations: Paginated<Reservation> | null; timeline: { groups: { product_id: number; product: string; rows: Reservation[] }[] } | null; view: string; filters: { search: string | null; status: string | null }; statuses: { value: string; label: string }[] }>();
const { can } = usePermissions();
const { datetime } = useFormat();
const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? '');
const apply = (view = props.view) => router.get(route('reservations.index'), { search: search.value || undefined, status: status.value || undefined, view }, { preserveState: true });
</script>
<template>
    <PageHeader title="Reservations"><template #actions><Link v-if="can('reservations.reservations.create')" class="st-link" :href="route('reservations.create')">New reservation</Link></template></PageHeader>
    <form class="mb-4 flex flex-wrap gap-2" @submit.prevent="apply()"><input v-model="search" class="st-input max-w-64" placeholder="Search reservations" aria-label="Search reservations"><select v-model="status" class="st-input max-w-48" aria-label="Status"><option value="">All statuses</option><option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option></select><button class="st-btn">Filter</button><button type="button" class="st-btn" @click="apply(view === 'list' ? 'timeline' : 'list')">{{ view === 'list' ? 'Timeline' : 'List' }}</button></form>
    <div v-if="reservations" class="st-card overflow-x-auto p-4"><p v-if="!reservations.data.length">No reservations found.</p><table v-else class="w-full text-left text-sm"><thead><tr><th>Reservation</th><th>Product</th><th>Quantity</th><th>Status</th><th>Period</th></tr></thead><tbody><tr v-for="r in reservations.data" :key="r.id" class="border-t border-line"><td class="py-2"><Link class="st-link" :href="route('reservations.show', r.id)">{{ r.label }}</Link></td><td>{{ r.product }}</td><td>{{ r.total }}</td><td><Badge :tone="reservationStatusTone[r.status]">{{ r.status }}</Badge></td><td>{{ datetime(r.starts_at) }} – {{ datetime(r.ends_at) }}</td></tr></tbody></table><Pagination :meta="reservations" /></div>
    <div v-else-if="timeline" class="space-y-3"><p v-if="!timeline.groups.length">No reservations in this period.</p><section v-for="group in timeline.groups" :key="group.product_id" class="st-card p-4"><h2 class="font-semibold">{{ group.product }}</h2><ul><li v-for="r in group.rows" :key="r.id" class="py-1"><Link class="st-link" :href="route('reservations.show', r.id)">{{ r.label }}</Link> · {{ r.total }} · {{ datetime(r.starts_at) }} – {{ datetime(r.ends_at) }}</li></ul></section></div>
</template>
<style scoped>.st-btn { border: 1px solid var(--st-line); border-radius: .375rem; padding: .375rem .75rem; background: var(--st-surface); } .st-btn:hover { background: var(--st-hover); } .st-btn:disabled { opacity: .5; }</style>
