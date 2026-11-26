<script setup lang="ts">
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import { http } from '@/lib/http';
import type { Option } from '@/types';
const props = defineProps<{ filters: { from: string; to: string; location: number | null; product: number | null; bucket: string | null }; initial: Record<string, any> | null; suggestions: Record<string, any>[]; locations: Option[]; maxDays: number }>();
const from = ref(props.filters.from);
const to = ref(props.filters.to);
const location = ref<number | null>(props.filters.location);
const query = ref('');
const results = ref(props.suggestions);
const selectedBucket = ref(props.filters.bucket ?? 'available');
const error = ref('');
const loading = ref(false);
const buckets = ['total', 'available', 'reserved', 'checked_out', 'tentative', 'maintenance', 'damaged', 'lost', 'retired'];
const label = (value: string) => value.replaceAll('_', ' ');
const search = async () => {
    loading.value = true; error.value = '';
    try {
        const response = await http.get('/availability/search', { params: { q: query.value, from: from.value, to: to.value } });
        results.value = response.data.data;
    } catch { error.value = 'Unable to search. Check the date range and try again.'; }
    finally { loading.value = false; }
};
const open = (product: number | null) => router.get('/availability', { from: from.value, to: to.value, location: location.value, product }, { preserveScroll: true });
</script>
<template>
    <PageHeader title="Availability" :description="`Explore up to ${maxDays} days of availability.`" />
    <div class="space-y-4">
        <Card title="Date range and location">
            <form class="flex flex-wrap items-end gap-3" @submit.prevent="open(filters.product)">
                <label class="st-label">From <input v-model="from" class="st-input" type="date" required></label>
                <label class="st-label">To <input v-model="to" class="st-input" type="date" :min="from" required></label>
                <label class="st-label">Location <select v-model="location" class="st-input"><option :value="null">All locations</option><option v-for="o in locations" :key="o.value" :value="o.value">{{ o.label }}</option></select></label>
                <Button type="submit">Apply</Button>
            </form>
            <p class="mt-2 text-xs text-muted">The availability calculation is global; location filters the physical stock distribution only.</p>
        </Card>
        <Card title="Find a product">
            <form class="flex gap-2" @submit.prevent="search"><label class="sr-only" for="product-query">Search products</label><input id="product-query" v-model="query" class="st-input max-w-md" type="search" placeholder="Name, SKU, barcode or serial"><Button type="submit" :loading="loading">Search</Button></form>
            <p v-if="error" class="mt-2 text-red-600" role="alert">{{ error }}</p>
            <ul class="mt-3 space-y-2"><li v-for="item in results" :key="item.id"><button type="button" class="text-accent hover:underline" @click="open(item.id)">{{ item.name }}</button> · {{ item.sku || 'No SKU' }} · {{ item.available }} available</li><li v-if="!results.length" class="text-muted">No products found.</li></ul>
        </Card>
        <template v-if="initial">
            <Card :title="initial.product.name" :description="initial.product.tracking_label">
                <div class="flex flex-wrap gap-2"><button v-for="bucket in buckets" :key="bucket" type="button" :aria-pressed="selectedBucket === bucket" class="rounded border border-line px-3 py-2 text-sm capitalize hover:bg-hover" @click="selectedBucket = bucket">{{ label(bucket) }}: {{ initial.breakdown[bucket] }}</button></div>
                <h3 class="mt-4 font-medium capitalize">{{ label(selectedBucket) }} details</h3>
                <ul class="mt-2 space-y-1"><li v-for="(source, i) in initial.breakdown.sources[selectedBucket]" :key="i"><Link v-if="source.url" :href="source.url" class="text-accent hover:underline">{{ source.label }}</Link><span v-else>{{ source.label }}</span>: {{ source.quantity }}</li><li v-if="!initial.breakdown.sources[selectedBucket]?.length" class="text-muted">No sources.</li></ul>
            </Card>
            <Card title="Physical stock by location"><ul><li v-for="row in initial.locations" :key="row.location_id ?? 'unassigned'">{{ row.location }}: {{ row.owned }} owned, {{ row.serviceable }} serviceable</li><li v-if="!initial.locations.length" class="text-muted">No stock at this location.</li></ul></Card>
            <Card title="Daily availability"><div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr><th>Date</th><th>Committed</th><th>Tentative</th><th>Available</th></tr></thead><tbody><tr v-for="day in initial.timeline" :key="day.date" class="border-t border-line"><td class="py-1">{{ day.date }}</td><td>{{ day.committed }}</td><td>{{ day.tentative }}</td><td>{{ day.available }}</td></tr></tbody></table></div></Card>
        </template>
    </div>
</template>
