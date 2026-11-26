<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
type Line = { id: number; product: string; sku: string | null; location: string | null; required: number; spare: number; pull: number; picked: number; children: Line[] };
defineProps<{ job: { id: number; number: string; name: string }; sheet: { sections: { name: string; lines: Line[] }[]; totals: { pull: number; picked: number } } }>();
</script>
<template>
    <PageHeader :title="`Pull sheet · ${job.number} ${job.name}`"><template #actions><Link class="st-link" :href="route('jobs.show', job.id)">Back to job</Link></template></PageHeader>
    <p class="mb-4">Picked {{ sheet.totals.picked }} / {{ sheet.totals.pull }}</p>
    <p v-if="!sheet.sections.length">No equipment allocated.</p>
    <section v-for="section in sheet.sections" :key="section.name" class="st-card mb-4 overflow-x-auto p-4"><h2 class="font-semibold">{{ section.name }}</h2><table class="w-full text-left text-sm"><thead><tr><th>Product</th><th>Location</th><th>Required</th><th>Spare</th><th>Pull</th><th>Picked</th></tr></thead><tbody><template v-for="line in section.lines" :key="line.id"><tr class="border-t border-line"><td class="py-2">{{ line.product }} {{ line.sku }}</td><td>{{ line.location ?? '—' }}</td><td>{{ line.required }}</td><td>{{ line.spare }}</td><td>{{ line.pull }}</td><td>{{ line.picked }}</td></tr><tr v-for="child in line.children" :key="child.id" class="border-t border-line"><td class="py-2 pl-4">↳ {{ child.product }} {{ child.sku }}</td><td>{{ child.location ?? '—' }}</td><td>{{ child.required }}</td><td>{{ child.spare }}</td><td>{{ child.pull }}</td><td>{{ child.picked }}</td></tr></template></tbody></table></section>
</template>
