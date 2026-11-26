<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import { usePermissions } from '@/composables/usePermissions';
defineProps<{ rules: { id: number; name: string; severity: string; is_active: boolean; summary: string }[]; counts: { total: number; active: number; errors: number } }>();
const { can } = usePermissions();
const remove = (id: number) => { if (confirm('Delete this rule?')) router.delete(route('rules.destroy', id)); };
</script>
<template>
    <PageHeader title="Validation rules" :description="`${counts.active} active of ${counts.total} rules · ${counts.errors} errors`"><template #actions><Button v-if="can('rules.rules.create')" variant="primary" :href="route('rules.create')">New rule</Button></template></PageHeader>
    <div class="st-card p-4"><p v-if="!rules.length">No rules found.</p><ul class="divide-y divide-line"><li v-for="r in rules" :key="r.id" class="flex flex-wrap justify-between gap-3 py-3"><div><p class="font-medium">{{ r.name }} · {{ r.severity }} · {{ r.is_active ? 'Active' : 'Inactive' }}</p><p class="text-sm text-muted">{{ r.summary }}</p></div><div class="flex gap-2"><Button v-if="can('rules.rules.edit')" :href="route('rules.edit', r.id)">Edit</Button><Button v-if="can('rules.rules.edit')" :href="route('rules.toggle', r.id)" method="patch">{{ r.is_active ? 'Disable' : 'Enable' }}</Button><Button v-if="can('rules.rules.delete')" variant="danger" @click="remove(r.id)">Delete</Button></div></li></ul></div>
</template>
