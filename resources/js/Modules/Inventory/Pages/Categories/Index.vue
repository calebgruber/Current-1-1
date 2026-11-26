<script setup lang="ts">
import { ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import { usePermissions } from '@/composables/usePermissions';

interface Category {
    id: number;
    parent_id: number | null;
    name: string;
    path: string;
    color: string | null;
    icon: string | null;
    sort_order: number;
    products_count: number;
}

defineProps<{ categories: Category[]; parentOptions: { value: number; label: string }[] }>();
const { can } = usePermissions();
const editing = ref<number | null>(null);
const visible = ref(false);
const form = useForm({ name: '', parent_id: null as number | null, color: '', icon: '', sort_order: 0 });

function open(category?: Category) {
    editing.value = category?.id ?? null;
    form.clearErrors();
    form.name = category?.name ?? '';
    form.parent_id = category?.parent_id ?? null;
    form.color = category?.color ?? '';
    form.icon = category?.icon ?? '';
    form.sort_order = category?.sort_order ?? 0;
    visible.value = true;
}

function submit() {
    const options = { preserveScroll: true, onSuccess: () => { visible.value = false; } };
    if (editing.value !== null) form.put(route('inventory.categories.update', editing.value), options);
    else form.post(route('inventory.categories.store'), options);
}

function remove(category: Category) {
    if (window.confirm(`Delete "${category.name}"? Its children will be moved to its parent.`)) {
        router.delete(route('inventory.categories.destroy', category.id), { preserveScroll: true });
    }
}
</script>

<template>
    <PageHeader title="Categories" description="Organize products into a category hierarchy.">
        <template #actions>
            <Button v-if="can('inventory.categories.create')" variant="primary" @click="open()">Add category</Button>
            <Button :href="route('inventory.manufacturers.index')">Manufacturers</Button>
        </template>
    </PageHeader>
    <form v-if="visible" class="st-card mb-4 max-w-2xl space-y-4 p-5" @submit.prevent="submit">
        <h2 class="font-semibold">{{ editing === null ? 'New category' : 'Edit category' }}</h2>
        <div v-if="Object.keys(form.errors).length" class="text-sm text-red-600" role="alert">
            <p v-for="(error, key) in form.errors" :key="key">{{ error }}</p>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <label class="st-label">Name <input v-model="form.name" class="st-input" maxlength="120" required></label>
            <label class="st-label">Parent category
                <select v-model="form.parent_id" class="st-input">
                    <option :value="null">None</option>
                    <option v-for="option in parentOptions.filter(o => o.value !== editing)" :key="option.value" :value="option.value">{{ option.label }}</option>
                </select>
            </label>
            <label class="st-label">Color (hex) <input v-model="form.color" class="st-input" placeholder="#123456" pattern="#[0-9a-fA-F]{6}"></label>
            <label class="st-label">Icon <input v-model="form.icon" class="st-input" maxlength="50" pattern="[A-Za-z0-9-]+"></label>
            <label class="st-label">Sort order <input v-model.number="form.sort_order" class="st-input" type="number" min="0" max="100000"></label>
        </div>
        <div class="flex gap-2"><Button type="submit" variant="primary" :loading="form.processing">Save</Button><Button @click="visible = false">Cancel</Button></div>
    </form>
    <div class="st-card overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead><tr class="border-b border-line"><th class="p-3">Category</th><th class="p-3">Products</th><th class="p-3"><span class="sr-only">Actions</span></th></tr></thead>
            <tbody>
                <tr v-for="category in categories" :key="category.id" class="border-b border-line">
                    <td class="p-3">{{ category.path }}</td><td class="p-3">{{ category.products_count }}</td>
                    <td class="space-x-2 p-3 text-right">
                        <Button v-if="can('inventory.categories.edit')" @click="open(category)">Edit</Button>
                        <Button v-if="can('inventory.categories.delete')" @click="remove(category)">Delete</Button>
                    </td>
                </tr>
                <tr v-if="!categories.length"><td colspan="3" class="p-4 text-muted">No categories yet.</td></tr>
            </tbody>
        </table>
    </div>
    <Link v-if="can('inventory.products.view')" :href="route('inventory.products.index')" class="mt-4 inline-block text-sm text-accent hover:underline">Back to products</Link>
</template>
