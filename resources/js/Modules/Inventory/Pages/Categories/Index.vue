<script setup lang="ts">
import { computed, ref } from 'vue';
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

const props = defineProps<{ categories: Category[]; parentOptions: { value: number; label: string }[]; treeVersion: number }>();
const { can } = usePermissions();
const editing = ref<number | null>(null);
const visible = ref(false);
const form = useForm({ name: '', parent_id: null as number | null, color: '', icon: '', sort_order: 0, version: props.treeVersion });
const dragging = ref<number | null>(null);
const moving = ref<Category | null>(null);
const destination = ref<number | null>(null);
const before = ref<number | null>(null);
const busy = ref(false);
const moveMessage = ref('');

function isDescendant(id: number, ancestor: number): boolean {
    const visited = new Set<number>();
    let current: number | null = id;
    while (current !== null && !visited.has(current)) {
        if (current === ancestor) return true;
        visited.add(current);
        current = props.categories.find(c => c.id === current)?.parent_id ?? null;
    }
    return false;
}

const destinations = computed(() => props.categories.filter(c => moving.value && !isDescendant(c.id, moving.value.id)));
const destinationSiblings = computed(() => props.categories.filter(c => c.parent_id === destination.value && c.id !== moving.value?.id));

function startMove(category: Category) {
    moving.value = category;
    destination.value = category.parent_id;
    const siblings = props.categories.filter(c => c.parent_id === category.parent_id);
    before.value = siblings[siblings.findIndex(c => c.id === category.id) + 1]?.id ?? null;
}

function move(id: number, parentId: number | null, beforeId: number | null) {
    if (busy.value) return;
    busy.value = true;
    moveMessage.value = '';
    router.put(route('inventory.categories.move'), { id, parent_id: parentId, before_id: beforeId, version: props.treeVersion }, {
        preserveScroll: true,
        onSuccess: () => { moving.value = null; moveMessage.value = 'Category moved.'; },
        onError: errors => { moveMessage.value = Object.values(errors).join(' '); },
        onFinish: () => { busy.value = false; dragging.value = null; },
    });
}

function moveSibling(category: Category, direction: -1 | 1) {
    const siblings = props.categories.filter(c => c.parent_id === category.parent_id);
    const index = siblings.findIndex(c => c.id === category.id);
    if (direction === -1 && index > 0) move(category.id, category.parent_id, siblings[index - 1].id);
    if (direction === 1 && index < siblings.length - 1) move(category.id, category.parent_id, siblings[index + 2]?.id ?? null);
}

function siblingEdge(category: Category, direction: -1 | 1): boolean {
    const siblings = props.categories.filter(c => c.parent_id === category.parent_id);
    const index = siblings.findIndex(c => c.id === category.id);
    return direction === -1 ? index <= 0 : index >= siblings.length - 1;
}

function dropBefore(category: Category) {
    if (dragging.value !== null && dragging.value !== category.id) move(dragging.value, category.parent_id, category.id);
}

function dropInside(category: Category) {
    if (dragging.value !== null && !isDescendant(category.id, dragging.value)) move(dragging.value, category.id, null);
}

function open(category?: Category) {
    editing.value = category?.id ?? null;
    form.clearErrors();
    form.name = category?.name ?? '';
    form.parent_id = category?.parent_id ?? null;
    form.color = category?.color ?? '';
    form.icon = category?.icon ?? '';
    form.sort_order = category?.sort_order ?? 0;
    form.version = props.treeVersion;
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
    <p v-if="can('inventory.categories.edit')" class="mb-3 text-sm text-muted">Drag a category to “Before” or “Inside”, or use the move controls to reorder and change its parent.</p>
    <div v-if="can('inventory.categories.edit') && dragging !== null" class="mb-3 rounded border border-line p-3" @dragover.prevent @drop.prevent="move(dragging!, null, null)">Drop here to move to the end of the top level</div>
    <p class="mb-3 text-sm" role="status" aria-live="polite">{{ moveMessage }}</p>
    <form v-if="moving && can('inventory.categories.edit')" class="st-card mb-4 max-w-2xl space-y-3 p-5" @submit.prevent="move(moving!.id, destination, before)">
        <h2 class="font-semibold">Move {{ moving.name }}</h2>
        <label class="st-label">New parent
            <select v-model="destination" class="st-input" @change="before = null">
                <option :value="null">Top level</option>
                <option v-for="category in destinations" :key="category.id" :value="category.id">{{ category.path }}</option>
            </select>
        </label>
        <label class="st-label">Position
            <select v-model="before" class="st-input">
                <option :value="null">At end</option>
                <option v-for="category in destinationSiblings" :key="category.id" :value="category.id">Before {{ category.name }}</option>
            </select>
        </label>
        <div class="flex gap-2"><Button type="submit" variant="primary" :loading="busy">Move</Button><Button @click="moving = null">Cancel</Button></div>
    </form>
    <div class="st-card overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead><tr class="border-b border-line"><th class="p-3">Category</th><th class="p-3">Products</th><th class="p-3"><span class="sr-only">Actions</span></th></tr></thead>
            <tbody>
                <tr v-for="category in categories" :key="category.id" class="border-b border-line" :draggable="can('inventory.categories.edit') && !busy" @dragstart="dragging = category.id; $event.dataTransfer?.setData('text/plain', String(category.id))" @dragend="dragging = null">
                    <td class="p-3">
                        {{ category.path }}
                        <div v-if="dragging !== null && dragging !== category.id" class="mt-2 flex gap-2">
                            <span class="rounded border border-line p-2" @dragover.prevent @drop.prevent.stop="dropBefore(category)">Drop before</span>
                            <span v-if="!isDescendant(category.id, dragging)" class="rounded border border-line p-2" @dragover.prevent @drop.prevent.stop="dropInside(category)">Drop inside</span>
                        </div>
                    </td><td class="p-3">{{ category.products_count }}</td>
                    <td class="space-x-2 p-3 text-right">
                        <Button v-if="can('inventory.categories.edit')" :disabled="busy || siblingEdge(category, -1)" :aria-label="`Move ${category.name} up`" @click="moveSibling(category, -1)">Up</Button>
                        <Button v-if="can('inventory.categories.edit')" :disabled="busy || siblingEdge(category, 1)" :aria-label="`Move ${category.name} down`" @click="moveSibling(category, 1)">Down</Button>
                        <Button v-if="can('inventory.categories.edit')" :disabled="busy" :aria-label="`Move ${category.name} to another position`" @click="startMove(category)">Move…</Button>
                        <Button v-if="can('inventory.categories.edit')" @click="open(category)">Edit</Button>
                        <Button v-if="can('inventory.categories.delete')" @click="remove(category)">Delete</Button>
                    </td>
                </tr>
                <tr v-if="!categories.length"><td colspan="3" class="p-4 text-muted">No categories yet.</td></tr>
            </tbody>
        </table>
    </div>
    <Link v-if="can('inventory.products.view')" :href="route('inventory.products.index')" class="st-link mt-4 text-sm">Back to products</Link>
</template>
