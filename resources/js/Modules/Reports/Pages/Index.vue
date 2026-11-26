<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import { usePermissions } from '@/composables/usePermissions';
defineProps<{ reports: { id: number; name: string; description: string | null; source_label: string; can_run: boolean; owner: string | null }[] }>();
const { can } = usePermissions();
</script>
<template>
    <PageHeader title="Reports"><template #actions><Button v-if="can('reporting.reports.create')" variant="primary" :href="route('reports.create')">New report</Button></template></PageHeader>
    <div class="st-card p-4"><p v-if="!reports.length">No reports found.</p><ul class="divide-y divide-line"><li v-for="r in reports" :key="r.id" class="py-3"><Link v-if="r.can_run" :href="route('reports.show', r.id)" class="st-link font-medium">{{ r.name }}</Link><span v-else class="font-medium">{{ r.name }} (source access required)</span><p class="text-sm text-muted">{{ r.source_label }} · {{ r.owner ?? 'Unknown owner' }}</p><p v-if="r.description" class="text-sm">{{ r.description }}</p></li></ul></div>
</template>
