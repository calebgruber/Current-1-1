<script setup lang="ts">
import Button from '@/Components/ui/Button.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { usePermissions } from '@/composables/usePermissions';
defineProps<{ record: Record<string, any> }>();
const { can } = usePermissions();
const label = (value: string) => value?.replaceAll('_', ' ') ?? '—';
</script>
<template>
    <PageHeader :title="`${record.reference} — ${record.title}`"><template #actions><Button v-if="can('maintenance.records.edit')" :href="`/maintenance/${record.id}/edit`">Edit</Button></template></PageHeader>
    <div class="st-card max-w-3xl space-y-4 p-5">
        <dl class="grid gap-4 sm:grid-cols-2">
            <div><dt class="text-muted">Product</dt><dd>{{ record.product?.name ?? '—' }}</dd></div>
            <div><dt class="text-muted">Asset / quantity</dt><dd>{{ record.asset?.asset_number ?? record.quantity }}</dd></div>
            <div><dt class="text-muted">Status</dt><dd class="capitalize">{{ label(record.status) }}</dd></div>
            <div><dt class="text-muted">Type / priority</dt><dd class="capitalize">{{ label(record.type) }} / {{ record.priority }}</dd></div>
            <div><dt class="text-muted">Assigned to</dt><dd>{{ record.assignee?.name ?? '—' }}</dd></div>
            <div><dt class="text-muted">Reported by</dt><dd>{{ record.reporter?.name ?? '—' }}</dd></div>
            <div><dt class="text-muted">Job</dt><dd>{{ record.job ? `${record.job.number} — ${record.job.name}` : '—' }}</dd></div>
            <div><dt class="text-muted">Cost</dt><dd>{{ record.cost ?? '—' }}</dd></div>
            <div><dt class="text-muted">Due</dt><dd>{{ record.due_at ? new Date(record.due_at).toLocaleString() : '—' }}</dd></div>
            <div><dt class="text-muted">Started</dt><dd>{{ record.started_at ? new Date(record.started_at).toLocaleString() : '—' }}</dd></div>
            <div><dt class="text-muted">Completed</dt><dd>{{ record.completed_at ? new Date(record.completed_at).toLocaleString() : '—' }}</dd></div>
        </dl>
        <p v-if="!record.asset_id" class="text-sm text-muted">This record does not make bulk stock unavailable. Adjust stock quantities separately in inventory.</p>
        <div><h2 class="font-semibold">Description</h2><p class="whitespace-pre-wrap">{{ record.description || '—' }}</p></div>
        <div><h2 class="font-semibold">Resolution</h2><p class="whitespace-pre-wrap">{{ record.resolution || '—' }}</p></div>
    </div>
</template>
