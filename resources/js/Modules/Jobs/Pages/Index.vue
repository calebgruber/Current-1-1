<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Pagination from '@/Components/ui/Pagination.vue';
import { usePermissions } from '@/composables/usePermissions';
import { useFormat } from '@/composables/useFormat';
import type { Paginated } from '@/types';

type Job = { id: number; number: string; name: string; stage_label: string; client: string | null; starts_at: string | null };
const props = defineProps<{ jobs: Paginated<Job> | null; board: Record<string, Job[]> | null; view: string; filters: { search: string | null; stage: string | null }; stages: { value: string; label: string }[] }>();
const { can } = usePermissions();
const { date } = useFormat();
const search = ref(props.filters.search ?? '');
const stage = ref(props.filters.stage ?? '');
const apply = (view = props.view) => router.get(route('jobs.index'), { search: search.value || undefined, stage: stage.value || undefined, view }, { preserveState: true });
</script>

<template>
    <PageHeader title="Jobs" description="Manage productions and equipment allocations.">
        <template #actions><Link v-if="can('jobs.jobs.create')" class="st-link" :href="route('jobs.create')">New job</Link></template>
    </PageHeader>
    <form class="mb-4 flex flex-wrap gap-2" @submit.prevent="apply()">
        <input v-model="search" class="st-input max-w-64" placeholder="Search jobs" aria-label="Search jobs">
        <select v-model="stage" class="st-input max-w-48" aria-label="Stage"><option value="">All stages</option><option v-for="s in stages" :key="s.value" :value="s.value">{{ s.label }}</option></select>
        <button class="st-btn" type="submit">Filter</button>
        <button class="st-btn" type="button" @click="apply(view === 'list' ? 'board' : 'list')">{{ view === 'list' ? 'Board view' : 'List view' }}</button>
    </form>
    <div v-if="jobs" class="st-card overflow-x-auto p-4">
        <p v-if="!jobs.data.length">No jobs found.</p>
        <table v-else class="w-full text-left text-sm"><thead><tr><th>Job</th><th>Stage</th><th>Client</th><th>Start</th></tr></thead><tbody>
            <tr v-for="job in jobs.data" :key="job.id" class="border-t border-line"><td class="py-2"><Link class="st-link" :href="route('jobs.show', job.id)">{{ job.number }} · {{ job.name }}</Link></td><td>{{ job.stage_label }}</td><td>{{ job.client ?? '—' }}</td><td>{{ date(job.starts_at) }}</td></tr>
        </tbody></table>
        <Pagination :meta="jobs" />
    </div>
    <div v-else-if="board" class="grid gap-4 md:grid-cols-3">
        <section v-for="s in stages.filter(s => board?.[s.value])" :key="s.value" class="st-card p-4"><h2 class="font-semibold">{{ s.label }}</h2><p v-if="!board[s.value]?.length" class="text-muted">No jobs</p><ul><li v-for="job in board[s.value]" :key="job.id" class="py-2"><Link class="st-link" :href="route('jobs.show', job.id)">{{ job.number }} · {{ job.name }}</Link></li></ul></section>
    </div>
</template>
<style scoped>.st-btn { border: 1px solid var(--st-line); border-radius: .375rem; padding: .375rem .75rem; background: var(--st-surface); } .st-btn:hover { background: var(--st-hover); } .st-btn:disabled { opacity: .5; }</style>
