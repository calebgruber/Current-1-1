<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import Pagination from '@/Components/ui/Pagination.vue';
import { usePermissions } from '@/composables/usePermissions';
import type { Paginated } from '@/types';
const props = defineProps<{ contacts: Paginated<Record<string, any>>; filters: { search: string } }>();
const { can } = usePermissions();
const search = ref(props.filters.search);
const records = computed<Paginated<Record<string, any>>>(() => ({ ...props.contacts, data: props.contacts.data.map(c => ({ ...c, organization_name: c.organization?.name })) }));
const columns = [{ key: 'name', label: 'Name' }, { key: 'type', label: 'Type' }, { key: 'organization_name', label: 'Organization' }, { key: 'email', label: 'Email' }, { key: 'phone', label: 'Phone' }];
</script>
<template>
    <PageHeader title="Contacts"><template #actions><Button v-if="can('contacts.contacts.create')" href="/contacts/create" variant="primary">Add contact</Button></template></PageHeader>
    <form class="mb-4 flex gap-2" @submit.prevent="router.get('/contacts', { search }, { preserveState: true, replace: true })"><label class="sr-only" for="search">Search contacts</label><input id="search" v-model="search" class="st-input max-w-sm" type="search" placeholder="Search contacts"><Button type="submit">Search</Button></form>
    <div class="st-card overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr class="border-b border-line"><th v-for="col in columns" :key="col.key" class="p-3">{{ col.label }}</th></tr></thead><tbody><tr v-for="c in records.data" :key="c.id" class="border-b border-line"><td v-for="(col, i) in columns" :key="col.key" class="p-3"><Link v-if="i === 0" class="st-link" :href="`/contacts/${c.id}`">{{ c[col.key] }}</Link><template v-else>{{ c[col.key] ?? '—' }}</template></td></tr><tr v-if="!records.data.length"><td class="p-4 text-muted" :colspan="columns.length">No contacts found.</td></tr></tbody></table></div>
    <Pagination :meta="records" />
</template>
