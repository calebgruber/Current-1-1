<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import CustomFieldDisplay from '@/Components/custom-fields/CustomFieldDisplay.vue';
import { usePermissions } from '@/composables/usePermissions';
import type { CustomFieldSchema, Option } from '@/types';
const props = defineProps<{ asset: Record<string, any>; statuses: Option[]; conditions: Option[]; maintenance: Record<string, any>[]; timeline: Record<string, any>[]; conditionReports: Record<string, any>[]; customFieldSchema: CustomFieldSchema[]; customFields: Record<string, any> }>();
const { can } = usePermissions();
const status = useForm({ status: props.asset.status, notes: '' });
const report = useForm({ condition: props.asset.condition, notes: '' });
</script>
<template>
    <PageHeader :title="asset.asset_number" :description="asset.status_label"><template #actions><Button v-if="can('assets.assets.edit')" :href="`/assets/${asset.id}/edit`">Edit</Button></template></PageHeader>
    <div class="space-y-4">
        <Card title="Details"><p>Product: <Link v-if="asset.product && can('inventory.products.view')" :href="`/inventory/products/${asset.product.id}`" class="text-accent hover:underline">{{ asset.product.name }}</Link><span v-else>{{ asset.product?.name || '—' }}</span></p><p>Serial: {{ asset.serial_number || '—' }} · Condition: {{ asset.condition }} · Location: {{ asset.location?.path || 'Unassigned' }}</p><p v-if="asset.notes" class="mt-2">{{ asset.notes }}</p><CustomFieldDisplay :schema="customFieldSchema" :values="customFields" /></Card>
        <Card v-if="can('assets.status.edit')" title="Change status"><form class="space-y-3" @submit.prevent="status.put(`/assets/${asset.id}/status`, { preserveScroll: true })"><div v-if="Object.keys(status.errors).length" class="text-red-600" role="alert"><p v-for="(error, key) in status.errors" :key="key">{{ error }}</p></div><label class="st-label">Status <select v-model="status.status" class="st-input"><option v-for="o in statuses" :key="o.value" :value="o.value">{{ o.label }}</option></select></label><label class="st-label">Notes <input v-model="status.notes" class="st-input"></label><Button type="submit" :loading="status.processing">Update status</Button></form></Card>
        <Card title="Condition reports"><ul><li v-for="r in conditionReports" :key="r.id">{{ r.condition }} — {{ r.notes || 'No notes' }}</li><li v-if="!conditionReports.length" class="text-muted">No reports.</li></ul><form v-if="can('assets.assets.edit')" class="mt-4 space-y-3" @submit.prevent="report.post(`/assets/${asset.id}/condition-reports`, { preserveScroll: true, onSuccess: () => report.reset('notes') })"><div v-if="Object.keys(report.errors).length" class="text-red-600" role="alert"><p v-for="(error, key) in report.errors" :key="key">{{ error }}</p></div><label class="st-label">Condition <select v-model="report.condition" class="st-input"><option v-for="o in conditions" :key="o.value" :value="o.value">{{ o.label }}</option></select></label><label class="st-label">Notes <textarea v-model="report.notes" class="st-input" rows="2" /></label><Button type="submit" :loading="report.processing">Add report</Button></form></Card>
        <Card title="Maintenance"><ul><li v-for="m in maintenance" :key="m.id">{{ m.reference }} — {{ m.title }} ({{ m.status_label }})</li><li v-if="!maintenance.length" class="text-muted">No maintenance records.</li></ul></Card>
        <Card title="Status history"><ul><li v-for="event in timeline" :key="event.id">{{ event.created_at }}: {{ event.to_status }} <span v-if="event.notes">— {{ event.notes }}</span></li><li v-if="!timeline.length" class="text-muted">No history.</li></ul></Card>
    </div>
</template>
