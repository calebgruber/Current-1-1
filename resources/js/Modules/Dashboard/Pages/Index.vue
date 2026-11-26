<script setup lang="ts">
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { AlertTriangle, ArrowRight, Briefcase, Gauge, ScanBarcode, Wrench } from 'lucide-vue-next';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Card from '@/Components/ui/Card.vue';
import Badge from '@/Components/ui/Badge.vue';
import StatTile from '@/Components/ui/StatTile.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import Button from '@/Components/ui/Button.vue';
import { jobStageTone, jobStages, assetStatusTone, humanize } from '@/lib/status';
import { useFormat } from '@/composables/useFormat';
import type { SharedProps } from '@/types';

interface JobRow {
    id: number;
    number: string;
    name: string;
    stage: string;
    stage_label: string;
    starts_at: string | null;
    ends_at: string | null;
    prep_starts_at: string | null;
    client: string | null;
    url: string | null;
}

const props = defineProps<{
    stageCounts: Record<string, number> | null;
    upcoming: JobRow[] | null;
    warehouseQueue: JobRow[] | null;
    overdue: JobRow[] | null;
    assetCounts: Record<string, number> | null;
    maintenanceOpen: number | null;
    recentScans: { id: number; mode: string; code: string; result: string; message: string | null; user: string | null; at: string | null }[] | null;
}>();

const page = usePage<SharedProps>();
const { date, relative } = useFormat();

const greeting = computed(() => {
    const h = new Date().getHours();
    return h < 12 ? 'Good morning' : h < 18 ? 'Good afternoon' : 'Good evening';
});
const activeStages = jobStages.filter((s) => !['closed', 'archived'].includes(s.value));
const resultTone = (r: string) => (r === 'success' ? 'success' : r === 'warning' ? 'warning' : 'danger');
const has = (name: string) => route().has(name);
</script>

<template>
    <PageHeader :title="`${greeting}, ${page.props.auth.user?.name.split(' ')[0] ?? ''}`" description="Here’s what’s happening across your productions.">
        <template #actions>
            <Button v-if="has('availability.index')" :href="route('availability.index')"><Gauge class="size-4" /> Availability</Button>
            <Button v-if="has('warehouse.index')" :href="route('warehouse.index')"><ScanBarcode class="size-4" /> Warehouse</Button>
            <Button v-if="has('jobs.create')" variant="primary" :href="route('jobs.create')"><Briefcase class="size-4" /> New job</Button>
        </template>
    </PageHeader>

    <div class="space-y-6">
        <section v-if="stageCounts" aria-label="Job pipeline">
            <div class="grid grid-cols-3 gap-2 sm:grid-cols-5 xl:grid-cols-9">
                <Link
                    v-for="stage in activeStages"
                    :key="stage.value"
                    :href="has('jobs.index') ? route('jobs.index', { stage: stage.value }) : '#'"
                    class="st-card p-3 transition hover:border-line-strong"
                >
                    <Badge :tone="jobStageTone[stage.value]" dot>{{ stage.label }}</Badge>
                    <div class="st-num mt-2 text-2xl font-semibold">{{ stageCounts[stage.value] ?? 0 }}</div>
                </Link>
            </div>
        </section>

        <div class="grid gap-6 xl:grid-cols-3">
            <Card v-if="upcoming" title="Upcoming in the next 3 weeks" class="xl:col-span-2" :padded="false">
                <template #actions><Link v-if="has('jobs.index')" :href="route('jobs.index')" class="st-link text-xs">All jobs</Link></template>
                <EmptyState v-if="!upcoming.length" title="Nothing scheduled" description="Jobs with prep or show dates in the next 21 days appear here." />
                <ul v-else class="divide-y divide-line">
                    <li v-for="job in upcoming" :key="job.id">
                        <component :is="job.url ? Link : 'div'" :href="job.url ?? undefined" class="flex items-center gap-3 px-4 py-3 hover:bg-hover/60">
                            <span class="w-20 shrink-0 font-mono text-xs text-muted">{{ job.number }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-medium">{{ job.name }}</span>
                                <span class="block truncate text-xs text-muted">{{ job.client ?? 'No client' }} · {{ date(job.starts_at) }} → {{ date(job.ends_at) }}</span>
                            </span>
                            <Badge :tone="jobStageTone[job.stage]">{{ job.stage_label }}</Badge>
                        </component>
                    </li>
                </ul>
            </Card>

            <div class="space-y-6">
                <Card v-if="overdue?.length" title="Overdue returns" :padded="false">
                    <template #actions><AlertTriangle class="size-4 text-amber-500" /></template>
                    <ul class="divide-y divide-line">
                        <li v-for="job in overdue" :key="job.id" class="flex items-center justify-between gap-2 px-4 py-2.5">
                            <component :is="job.url ? Link : 'span'" :href="job.url ?? undefined" class="truncate text-sm hover:underline">{{ job.number }} · {{ job.name }}</component>
                            <span class="shrink-0 text-xs text-red-600 dark:text-red-400">due {{ relative(job.ends_at) }}</span>
                        </li>
                    </ul>
                </Card>

                <div v-if="assetCounts || maintenanceOpen !== null" class="grid grid-cols-2 gap-3">
                    <StatTile v-if="maintenanceOpen !== null" label="Open maintenance" :value="maintenanceOpen" :icon="Wrench" :tone="maintenanceOpen ? 'warning' : 'neutral'" :clickable="has('maintenance.index')" @click="has('maintenance.index') && $inertia.visit(route('maintenance.index'))" />
                    <template v-if="assetCounts">
                        <StatTile v-for="(count, status) in assetCounts" :key="status" :label="humanize(String(status))" :value="count" :tone="count ? assetStatusTone[status] : 'neutral'" :clickable="has('assets.index')" @click="has('assets.index') && $inertia.visit(route('assets.index', { status }))" />
                    </template>
                </div>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <Card v-if="warehouseQueue" title="Warehouse queue" description="Reserved, prepping, ready and out" :padded="false">
                <template #actions><Link v-if="has('warehouse.index')" :href="route('warehouse.index')" class="st-link inline-flex items-center gap-1 text-xs">Open warehouse <ArrowRight class="size-3" /></Link></template>
                <EmptyState v-if="!warehouseQueue.length" title="Queue is clear" />
                <ul v-else class="divide-y divide-line">
                    <li v-for="job in warehouseQueue" :key="job.id" class="flex items-center gap-3 px-4 py-2.5">
                        <Badge :tone="jobStageTone[job.stage]" dot>{{ job.stage_label }}</Badge>
                        <component :is="job.url ? Link : 'span'" :href="job.url ?? undefined" class="min-w-0 flex-1 truncate text-sm hover:underline">{{ job.number }} · {{ job.name }}</component>
                        <span class="text-xs text-muted">{{ date(job.prep_starts_at ?? job.starts_at) }}</span>
                    </li>
                </ul>
            </Card>

            <Card v-if="recentScans" title="Recent scans" :padded="false">
                <EmptyState v-if="!recentScans.length" title="No scans yet" :icon="ScanBarcode" />
                <ul v-else class="divide-y divide-line">
                    <li v-for="scan in recentScans" :key="scan.id" class="flex items-center gap-3 px-4 py-2.5 text-sm">
                        <Badge :tone="resultTone(scan.result)">{{ humanize(scan.mode) }}</Badge>
                        <span class="font-mono text-xs">{{ scan.code }}</span>
                        <span class="min-w-0 flex-1 truncate text-xs text-muted">{{ scan.message }}</span>
                        <span class="shrink-0 text-xs text-faint">{{ relative(scan.at) }}</span>
                    </li>
                </ul>
            </Card>
        </div>
    </div>
</template>
