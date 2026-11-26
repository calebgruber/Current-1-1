<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { useFormat } from '@/composables/useFormat';
type Job = { id: number; number: string; name: string; starts_at: string | null; picked: number; pull: number; checked_out: number; returned: number };
defineProps<{ queues: { stage: string; label: string; jobs: Job[] }[]; stations: { slug: string; label: string; allowed: boolean }[]; canDisplay: boolean }>();
const { date } = useFormat();
</script>
<template>
    <PageHeader title="Warehouse"><template #actions><Link v-if="canDisplay" class="st-link" :href="route('warehouse.display')">Fullscreen display</Link></template></PageHeader>
    <nav class="mb-5 flex flex-wrap gap-3" aria-label="Warehouse stations"><Link v-for="station in stations.filter(s => s.allowed)" :key="station.slug" class="st-link" :href="route(`warehouse.${station.slug}`)">{{ station.label }}</Link></nav>
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3"><section v-for="queue in queues" :key="queue.stage" class="st-card p-4"><h2 class="font-semibold">{{ queue.label }}</h2><p v-if="!queue.jobs.length" class="text-muted">No jobs</p><ul><li v-for="job in queue.jobs" :key="job.id" class="border-t border-line py-2"><p class="font-medium">{{ job.number }} · {{ job.name }}</p><p class="text-sm text-muted">{{ date(job.starts_at) }} · picked {{ job.picked }}/{{ job.pull }} · out {{ job.checked_out }} · returned {{ job.returned }}</p><div class="flex flex-wrap gap-2"><Link v-for="station in stations.filter(s => s.allowed)" :key="station.slug" class="st-link text-sm" :href="route(`warehouse.${station.slug}`, job.id)">{{ station.label }}</Link><Link v-if="canDisplay" class="st-link text-sm" :href="route('warehouse.display', job.id)">Display</Link></div></li></ul></section></div>
</template>
