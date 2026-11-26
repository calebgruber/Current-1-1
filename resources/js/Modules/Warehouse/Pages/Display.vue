<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref } from 'vue';
import FullscreenLayout from '@/Layouts/FullscreenLayout.vue';
import { http, errorMessage } from '@/lib/http';
defineOptions({ layout: FullscreenLayout });
type Job = { id: number; number: string; name: string; stage_label: string };
type Line = { id: number; product: string; pull: number; done: Record<string, number> };
const props = defineProps<{ job: Job | null; jobs: Job[]; lines: Line[]; totals: Record<string, number> | null; modes: { value: string; label: string; allowed: boolean }[]; initialMode: string }>();
const job = ref(props.job);
const lines = ref(props.lines);
const totals = ref(props.totals);
const mode = ref(props.initialMode);
const code = ref('');
const message = ref('');
const pending = ref(false);
let timer: ReturnType<typeof setInterval> | undefined;
const refresh = async () => {
    if (!job.value) return;
    try { const { data } = await http.get(route('warehouse.progress', job.value.id)); job.value = data.job; lines.value = data.lines; totals.value = data.totals; }
    catch (e) { message.value = errorMessage(e, 'Could not refresh progress.'); }
};
onMounted(() => { timer = setInterval(refresh, 15000); });
onUnmounted(() => { if (timer) clearInterval(timer); });
const changeJob = (event: Event) => { const id = (event.target as HTMLSelectElement).value; router.visit(id ? route('warehouse.display', { job: id, mode: mode.value }) : route('warehouse.display', { mode: mode.value })); };
const scan = async () => {
    if (pending.value || !code.value.trim()) return;
    pending.value = true;
    try {
        const { data } = await http.post(route('warehouse.scan'), { code: code.value, mode: mode.value, job_id: job.value?.id ?? null, station: 'display' });
        message.value = `${data.result.title}: ${data.result.message}`;
        code.value = '';
        if (data.select_job) { router.visit(route('warehouse.display', { job: data.select_job.id, mode: mode.value })); return; }
        if (data.progress) { job.value = data.progress.job; lines.value = data.progress.lines; totals.value = data.progress.totals; }
    } catch (e) { message.value = errorMessage(e, 'Scan failed.'); }
    finally { pending.value = false; }
};
</script>
<template>
    <main class="mx-auto max-w-7xl space-y-6 p-6"><header class="flex flex-wrap items-center justify-between gap-4"><h1 class="text-3xl font-semibold">Warehouse display</h1><Link class="text-indigo-300 underline" :href="route('dashboard')">Exit display</Link></header>
        <div class="flex flex-wrap gap-3"><label>Job <select :value="job?.id ?? ''" class="rounded bg-zinc-800 p-2" @change="changeJob"><option value="">Select a job</option><option v-for="j in jobs" :key="j.id" :value="j.id">{{ j.number }} · {{ j.name }}</option><option v-if="job && !jobs.some(j => j.id === job?.id)" :value="job.id">{{ job.number }} · {{ job.name }}</option></select></label><label>Mode <select v-model="mode" class="rounded bg-zinc-800 p-2"><option v-for="m in modes" :key="m.value" :value="m.value">{{ m.label }}{{ m.allowed ? '' : ' (read only)' }}</option></select></label></div>
        <h2 v-if="job" class="text-2xl">{{ job.number }} · {{ job.name }} — {{ job.stage_label }}</h2><p v-if="totals">Pull {{ totals.pull }} · Picked {{ totals.pick }} · Out {{ totals.checkout }} · Returned {{ totals.return }}</p>
        <form v-if="modes.some(m => m.value === mode && m.allowed)" class="flex flex-wrap gap-3" @submit.prevent="scan"><input v-model="code" class="rounded bg-zinc-800 p-3" placeholder="Scan barcode or job number" aria-label="Barcode" required><button class="rounded bg-indigo-600 px-4 py-2" :disabled="pending">Scan</button></form><p v-else>Read-only mode; scan permission required.</p><p v-if="message" role="status">{{ message }}</p>
        <div v-if="job" class="overflow-x-auto"><table class="w-full text-left text-lg"><thead><tr><th>Product</th><th>Pull</th><th>Pick</th><th>Prep</th><th>Stage</th><th>Out</th><th>Returned</th></tr></thead><tbody><tr v-for="line in lines" :key="line.id" class="border-t border-zinc-700"><td class="py-3">{{ line.product }}</td><td>{{ line.pull }}</td><td>{{ line.done.pick }}</td><td>{{ line.done.prep }}</td><td>{{ line.done.stage }}</td><td>{{ line.done.checkout }}</td><td>{{ line.done.return }}</td></tr></tbody></table><p v-if="!lines.length">No equipment allocated.</p></div><p v-else>Select or scan a job to begin.</p>
    </main>
</template>
