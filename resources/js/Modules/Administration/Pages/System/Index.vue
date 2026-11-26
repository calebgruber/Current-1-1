<script setup lang="ts">
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import Button from '@/Components/ui/Button.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { usePermissions } from '@/composables/usePermissions';

defineProps<{
    status: {
        database: 'ok' | 'unavailable';
        storage_writable: boolean;
        migrations: { status: 'available' | 'repository_missing' | 'unavailable'; applied: number | null; pending: string[] | null };
    };
}>();

const { can } = usePermissions();
const selected = ref<'config' | 'route' | 'view'>('view');
const open = ref(false);
const loading = ref(false);
const confirmClear = () => {
    router.post(route('admin.system.cache.clear'), { cache: selected.value }, {
        onStart: () => { loading.value = true; },
        onFinish: () => { loading.value = false; open.value = false; },
    });
};
</script>

<template>
    <PageHeader title="System" description="Read-only health and migration status, and framework cache maintenance." />
    <div class="space-y-4">
        <section class="st-card space-y-2 p-4">
            <h2 class="font-semibold">Health</h2>
            <p>Database connection: {{ status.database === 'ok' ? 'Available' : 'Unavailable' }}</p>
            <p>Storage directory: {{ status.storage_writable ? 'Writable' : 'Not writable' }}</p>
            <p class="text-sm text-muted">These checks do not test queues, backups, scheduler, or individual storage disks.</p>
        </section>
        <section class="st-card space-y-2 p-4">
            <h2 class="font-semibold">Migrations</h2>
            <template v-if="status.migrations.status === 'available'">
                <p>{{ status.migrations.applied }} recorded · {{ status.migrations.pending?.length }} pending</p>
                <ul v-if="status.migrations.pending?.length" class="list-inside list-disc text-sm">
                    <li v-for="name in status.migrations.pending" :key="name">{{ name }}</li>
                </ul>
            </template>
            <p v-else>{{ status.migrations.status === 'repository_missing' ? 'Migration repository is missing.' : 'Migration status is unavailable.' }}</p>
            <p class="text-sm text-muted">Status covers the default database and registered migration paths. No migrations are run here.</p>
        </section>
        <section v-if="can('admin.system.admin')" class="st-card space-y-3 p-4">
            <h2 class="font-semibold">Framework caches</h2>
            <p class="text-sm text-muted">Clears generated configuration, route, or compiled view files. Configuration and route caches must be rebuilt by your deployment process if needed. The application cache store is not flushed.</p>
            <div class="flex flex-wrap gap-2">
                <Button v-for="cache in (['config', 'route', 'view'] as const)" :key="cache" @click="selected = cache; open = true">Clear {{ cache }} cache</Button>
            </div>
        </section>
    </div>
    <ConfirmDialog v-model:open="open" title="Clear framework cache?" :message="`Clear the ${selected} cache?`" confirm-label="Clear cache" :loading="loading" @confirm="confirmClear" />
</template>
