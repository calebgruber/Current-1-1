<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Combobox from '@/Components/ui/Combobox.vue';
import { http, errorMessage } from '@/lib/http';
type Job = { id: number; number: string; name: string; stage_label: string };
type Line = { id: number; product: string; sku: string | null; pull: number; done: Record<string, number>; outstanding: number };
const props = defineProps<{ station: string; mode: string; modeLabel: string; job: Job | null; lines: Line[]; can: { scan: boolean }; stations: { slug: string; label: string; allowed: boolean }[] }>();
const selected = ref<number | string | null>(props.job?.id ?? null);
const code = ref('');
const quantity = ref(1);
const pending = ref(false);
const message = ref('');
const lines = ref(props.lines);
const selectJob = (id: string | number | null | undefined) => router.visit(id ? route(`warehouse.${props.station}`, id) : route(`warehouse.${props.station}`));
const scan = async () => {
    if (pending.value || !code.value.trim()) return;
    pending.value = true;
    try {
        const { data } = await http.post(route('warehouse.scan'), { mode: props.mode, code: code.value, quantity: quantity.value, job_id: props.job?.id ?? null, station: props.station });
        message.value = `${data.result.title}: ${data.result.message}`;
        code.value = '';
        if (data.select_job) { selectJob(data.select_job.id); return; }
        if (data.progress) lines.value = data.progress.lines;
    } catch (e) { message.value = errorMessage(e, 'Scan failed.'); }
    finally { pending.value = false; }
};
</script>
<template>
    <PageHeader :title="`${modeLabel} station`"><template #actions><Link class="st-link" :href="route('warehouse.index')">Warehouse</Link></template></PageHeader>
    <nav class="mb-4 flex flex-wrap gap-3"><Link v-for="s in stations.filter(s => s.allowed)" :key="s.slug" class="st-link" :href="job ? route(`warehouse.${s.slug}`, job.id) : route(`warehouse.${s.slug}`)">{{ s.label }}</Link></nav>
    <div class="space-y-4"><section class="st-card space-y-3 p-4"><label class="block">Job <Combobox v-model="selected" :endpoint="route('jobs.lookup', { type: 'jobs', warehouse: 1 })" :initial-label="job ? `${job.number} — ${job.name}` : null" @update:model-value="selectJob" /></label><p v-if="job">{{ job.stage_label }}</p><form v-if="can.scan" class="flex flex-wrap gap-2" @submit.prevent="scan"><input v-model="code" autofocus class="st-input max-w-80" placeholder="Scan barcode or job number" aria-label="Barcode" required><label>Quantity <input v-model.number="quantity" class="st-input w-24" type="number" min="1" max="1000" required></label><button class="st-btn" :disabled="pending">Scan</button></form><p v-else>Read-only station; scan permission required.</p><p v-if="message" role="status">{{ message }}</p></section>
        <section v-if="job" class="st-card overflow-x-auto p-4"><h2 class="mb-2 font-semibold">Progress</h2><p v-if="!lines.length">No equipment allocated.</p><table v-else class="w-full text-left text-sm"><thead><tr><th>Product</th><th>Pull</th><th>Pick</th><th>Prep</th><th>Stage</th><th>Out</th><th>Returned</th></tr></thead><tbody><tr v-for="line in lines" :key="line.id" class="border-t border-line"><td class="py-2">{{ line.product }} {{ line.sku }}</td><td>{{ line.pull }}</td><td>{{ line.done.pick }}</td><td>{{ line.done.prep }}</td><td>{{ line.done.stage }}</td><td>{{ line.done.checkout }}</td><td>{{ line.done.return }}</td></tr></tbody></table></section>
    </div>
</template>
<style scoped>.st-btn { border: 1px solid var(--st-line); border-radius: .375rem; padding: .375rem .75rem; background: var(--st-surface); } .st-btn:hover { background: var(--st-hover); } .st-btn:disabled { opacity: .5; }</style>
