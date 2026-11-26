<script setup lang="ts">
import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import Pagination from '@/Components/ui/Pagination.vue';
import { usePermissions } from '@/composables/usePermissions';
import type { Paginated } from '@/types';

interface Manufacturer { id: number; name: string; website: string | null; products_count: number }
const props = defineProps<{ manufacturers: Paginated<Manufacturer>; filters: { search: string } }>();
const { can } = usePermissions();
const search = ref(props.filters.search);
const editing = ref<number | null>(null);
const visible = ref(false);
const form = useForm({ name: '', website: '' });

function open(manufacturer?: Manufacturer) {
    editing.value = manufacturer?.id ?? null;
    form.clearErrors();
    form.name = manufacturer?.name ?? '';
    form.website = manufacturer?.website ?? '';
    visible.value = true;
}

function submit() {
    const options = { preserveScroll: true, onSuccess: () => { visible.value = false; } };
    if (editing.value !== null) form.put(route('inventory.manufacturers.update', editing.value), options);
    else form.post(route('inventory.manufacturers.store'), options);
}

function remove(manufacturer: Manufacturer) {
    if (window.confirm(`Delete "${manufacturer.name}"?`)) {
        router.delete(route('inventory.manufacturers.destroy', manufacturer.id), { preserveScroll: true });
    }
}
</script>

<template>
    <PageHeader title="Manufacturers">
        <template #actions>
            <Button v-if="can('inventory.categories.create')" variant="primary" @click="open()">Add manufacturer</Button>
            <Button :href="route('inventory.categories.index')">Categories</Button>
        </template>
    </PageHeader>
    <form v-if="visible" class="st-card mb-4 max-w-2xl space-y-4 p-5" @submit.prevent="submit">
        <h2 class="font-semibold">{{ editing === null ? 'New manufacturer' : 'Edit manufacturer' }}</h2>
        <div v-if="Object.keys(form.errors).length" class="text-sm text-red-600" role="alert">
            <p v-for="(error, key) in form.errors" :key="key">{{ error }}</p>
        </div>
        <label class="st-label">Name <input v-model="form.name" class="st-input" maxlength="255" required></label>
        <label class="st-label">Website <input v-model="form.website" class="st-input" type="url" maxlength="255"></label>
        <div class="flex gap-2"><Button type="submit" variant="primary" :loading="form.processing">Save</Button><Button @click="visible = false">Cancel</Button></div>
    </form>
    <form class="mb-4 flex gap-2" @submit.prevent="router.get(route('inventory.manufacturers.index'), { search }, { preserveState: true, replace: true })">
        <label class="sr-only" for="manufacturer-search">Search manufacturers</label>
        <input id="manufacturer-search" v-model="search" class="st-input max-w-sm" type="search" placeholder="Search manufacturers">
        <Button type="submit">Search</Button>
    </form>
    <div class="st-card overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead><tr class="border-b border-line"><th class="p-3">Name</th><th class="p-3">Website</th><th class="p-3">Products</th><th class="p-3"><span class="sr-only">Actions</span></th></tr></thead>
            <tbody>
                <tr v-for="manufacturer in manufacturers.data" :key="manufacturer.id" class="border-b border-line">
                    <td class="p-3">{{ manufacturer.name }}</td><td class="p-3">{{ manufacturer.website ?? '—' }}</td><td class="p-3">{{ manufacturer.products_count }}</td>
                    <td class="space-x-2 p-3 text-right">
                        <Button v-if="can('inventory.categories.edit')" @click="open(manufacturer)">Edit</Button>
                        <Button v-if="can('inventory.categories.delete')" @click="remove(manufacturer)">Delete</Button>
                    </td>
                </tr>
                <tr v-if="!manufacturers.data.length"><td colspan="4" class="p-4 text-muted">No manufacturers found.</td></tr>
            </tbody>
        </table>
    </div>
    <Pagination :meta="manufacturers" />
</template>
