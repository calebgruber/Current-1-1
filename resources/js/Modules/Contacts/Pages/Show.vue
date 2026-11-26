<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import CustomFieldDisplay from '@/Components/custom-fields/CustomFieldDisplay.vue';
import { usePermissions } from '@/composables/usePermissions';
import type { CustomFieldSchema } from '@/types';
defineProps<{ contact: Record<string, any>; members: Record<string, any>[]; jobs: Record<string, any>[]; customFieldSchema: CustomFieldSchema[]; customFields: Record<string, any> }>();
const { can } = usePermissions();
</script>
<template>
    <PageHeader :title="contact.name" :description="contact.type"><template #actions><Button v-if="can('contacts.contacts.edit')" :href="`/contacts/${contact.id}/edit`">Edit</Button></template></PageHeader>
    <div class="space-y-4">
        <Card title="Contact details"><p v-if="contact.organization">Organization: <Link :href="`/contacts/${contact.organization.id}`" class="st-link">{{ contact.organization.name }}</Link></p><p>Email: {{ contact.email || '—' }} · Phone: {{ contact.phone || '—' }}</p><p v-if="contact.title">{{ contact.title }}</p><p v-if="contact.address" class="whitespace-pre-line">{{ contact.address }}</p><p v-if="contact.notes" class="whitespace-pre-line">{{ contact.notes }}</p><CustomFieldDisplay :schema="customFieldSchema" :values="customFields" /></Card>
        <Card v-if="contact.type === 'organization'" title="People"><ul><li v-for="member in members" :key="member.id"><Link :href="`/contacts/${member.id}`" class="st-link">{{ member.name }}</Link> · {{ member.email || '—' }}</li><li v-if="!members.length" class="text-muted">No linked people.</li></ul></Card>
        <Card title="Jobs"><ul><li v-for="job in jobs" :key="job.id"><Link v-if="can('jobs.jobs.view')" :href="`/jobs/${job.id}`" class="st-link">{{ job.number }} — {{ job.name }}</Link><span v-else>{{ job.number }} — {{ job.name }}</span> ({{ job.role }})</li><li v-if="!jobs.length" class="text-muted">No linked jobs.</li></ul></Card>
    </div>
</template>
