@component('layouts.app')
<div class="mx-auto max-w-7xl space-y-5">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div><h1 class="text-2xl font-bold">Categories</h1><p class="text-sm text-gray-500">Organize products into a category hierarchy.</p></div>
        <div class="flex gap-2"><x-button :href="route('inventory.manufacturers.index')" color="secondary">Manufacturers</x-button><x-button :href="route('inventory.products.index')" color="secondary">Products</x-button></div>
    </div>
    @if(session('success'))<x-alert color="green">{{ session('success') }}</x-alert>@endif
    @if(session('error'))<x-alert color="red">{{ session('error') }}</x-alert>@endif
    @can('inventory.categories.create')<x-card bordered shadowless header="Add category">
        <form method="POST" action="{{ route('inventory.categories.store') }}" class="grid gap-3 sm:grid-cols-2">
            @csrf
            <label class="block text-sm">Name <x-input name="name" value="{{ old('name') }}" required maxlength="120" /></label>
            <label class="block text-sm">Parent category <select name="parent_id" class="mt-1 block w-full rounded border-gray-300"><option value="">None</option>@foreach($parentOptions as $option)<option value="{{ $option['value'] }}" @selected(old('parent_id') == $option['value'])>{{ $option['label'] }}</option>@endforeach</select></label>
            <label class="block text-sm">Color (hex) <x-input name="color" placeholder="#123456" pattern="#[0-9a-fA-F]{6}" value="{{ old('color') }}" /></label>
            <label class="block text-sm">Icon <x-input name="icon" maxlength="50" pattern="[A-Za-z0-9-]+" value="{{ old('icon') }}" /></label>
            <label class="block text-sm">Sort order <x-input name="sort_order" type="number" min="0" max="100000" value="{{ old('sort_order', 0) }}" /></label>
            <div class="sm:col-span-2"><x-button type="submit" color="primary">Add category</x-button></div>
            @if($errors->any())<div class="text-sm text-red-600 sm:col-span-2" role="alert">{{ $errors->first() }}</div>@endif
        </form>
    </x-card>@endcan
    @can('inventory.categories.edit')<p class="text-sm text-gray-600">Use each category’s move controls to reorder it, move it beneath a parent, or return it to the top level.</p>@endcan
    <x-card bordered shadowless>
        <div class="overflow-x-auto"><table class="w-full text-left text-sm">
            <thead><tr class="border-b"><th class="p-3">Category</th><th class="p-3">Products</th><th class="p-3">Actions</th></tr></thead>
            <tbody>
            @forelse($categories as $category)
                @php
                    $siblings = collect($categories)->where('parent_id', $category['parent_id'])->values();
                    $position = $siblings->search(fn ($row) => $row['id'] === $category['id']);
                    $previous = $position > 0 ? $siblings[$position - 1] : null;
                    $afterNext = $position < $siblings->count() - 2 ? $siblings[$position + 2]['id'] : null;
                    $excludedIds = [$category['id']];
                    do {
                        $addedDescendant = false;
                        foreach ($categories as $candidate) {
                            if ($candidate['parent_id'] !== null && in_array($candidate['parent_id'], $excludedIds, true) && ! in_array($candidate['id'], $excludedIds, true)) {
                                $excludedIds[] = $candidate['id'];
                                $addedDescendant = true;
                            }
                        }
                    } while ($addedDescendant);
                    $moveSiblings = collect($categories)->reject(fn ($candidate) => $candidate['id'] === $category['id'])
                        ->groupBy(fn ($candidate) => $candidate['parent_id'] === null ? '' : (string) $candidate['parent_id'])
                        ->map(fn ($rows) => $rows->map(fn ($row) => ['id' => $row['id'], 'name' => $row['name']])->values())
                        ->all();
                @endphp
                <tr class="border-b align-top" @can('inventory.categories.edit') draggable="true" data-category-id="{{ $category['id'] }}" data-parent-id="{{ $category['parent_id'] ?? '' }}" @endcan>
                    <td class="p-3"><span class="font-medium">{{ $category['path'] }}</span>
                        @can('inventory.categories.edit')
                        <div class="category-drop-targets mt-2 hidden gap-2 text-xs">
                            <button type="button" class="rounded border px-2 py-1" data-drop-before>Drop before</button>
                            <button type="button" class="rounded border px-2 py-1" data-drop-inside>Drop inside</button>
                        </div>
                        @endcan
                        @can('inventory.categories.edit')
                        <details class="mt-2"><summary class="cursor-pointer text-primary-700">Edit category</summary>
                            <form method="POST" action="{{ route('inventory.categories.update', $category['id']) }}" class="mt-2 grid gap-2 sm:grid-cols-2">
                                @csrf @method('PUT')<input type="hidden" name="version" value="{{ $treeVersion }}">
                                <label>Name <x-input name="name" value="{{ $category['name'] }}" required maxlength="120" /></label>
                                <label>Parent <select name="parent_id" class="block w-full rounded border-gray-300"><option value="">None</option>@foreach($parentOptions as $option)@if(!in_array($option['value'], $excludedIds, true))<option value="{{ $option['value'] }}" @selected($option['value'] === $category['parent_id'])>{{ $option['label'] }}</option>@endif @endforeach</select></label>
                                <label>Color <x-input name="color" value="{{ $category['color'] }}" placeholder="#123456" pattern="#[0-9a-fA-F]{6}" /></label>
                                <label>Icon <x-input name="icon" value="{{ $category['icon'] }}" maxlength="50" pattern="[A-Za-z0-9-]+" /></label>
                                <label>Sort order <x-input name="sort_order" type="number" min="0" max="100000" value="{{ $category['sort_order'] }}" /></label>
                                <div><x-button type="submit" color="primary">Save</x-button></div>
                            </form>
                        </details>
                        @endcan
                    </td>
                    <td class="p-3">{{ $category['products_count'] }}</td>
                    <td class="space-y-2 p-3">
                        @can('inventory.categories.edit')
                            @if($previous)
                            <form method="POST" action="{{ route('inventory.categories.move') }}" class="inline">@csrf @method('PUT')<input type="hidden" name="id" value="{{ $category['id'] }}"><input type="hidden" name="parent_id" value="{{ $category['parent_id'] }}"><input type="hidden" name="before_id" value="{{ $previous['id'] }}"><input type="hidden" name="version" value="{{ $treeVersion }}"><x-button type="submit" color="secondary">Move up</x-button></form>
                            @endif
                            @if($position < $siblings->count() - 1)
                            <form method="POST" action="{{ route('inventory.categories.move') }}" class="inline">@csrf @method('PUT')<input type="hidden" name="id" value="{{ $category['id'] }}"><input type="hidden" name="parent_id" value="{{ $category['parent_id'] }}"><input type="hidden" name="before_id" value="{{ $afterNext }}"><input type="hidden" name="version" value="{{ $treeVersion }}"><x-button type="submit" color="secondary">Move down</x-button></form>
                            @endif
                            <details><summary class="cursor-pointer text-primary-700">Move…</summary>
                                <form method="POST" action="{{ route('inventory.categories.move') }}" class="mt-2 space-y-2" x-data="{ parentId: '{{ $category['parent_id'] ?? '' }}', beforeId: '', siblingsByParent: @js($moveSiblings) }">@csrf @method('PUT')
                                    <input type="hidden" name="id" value="{{ $category['id'] }}"><input type="hidden" name="version" value="{{ $treeVersion }}">
                                    <label class="block">New parent <select name="parent_id" class="block w-full rounded border-gray-300" x-model="parentId" @change="beforeId = ''"><option value="">Top level</option>@foreach($parentOptions as $option)@if(!in_array($option['value'], $excludedIds, true))<option value="{{ $option['value'] }}" @selected($option['value'] === $category['parent_id'])>{{ $option['label'] }}</option>@endif @endforeach</select></label>
                                    <label class="block">Before <select name="before_id" class="block w-full rounded border-gray-300" x-model="beforeId"><option value="">At end</option>@verbatim<template x-for="sibling in (siblingsByParent[parentId] || [])" :key="sibling.id"><option :value="sibling.id" x-text="'Before ' + sibling.name"></option></template>@endverbatim</select></label>
                                    <x-button type="submit" color="primary">Move category</x-button>
                                </form>
                            </details>
                        @endcan
                        @can('inventory.categories.delete')
                            <form method="POST" action="{{ route('inventory.categories.destroy', $category['id']) }}" class="inline" onsubmit="return confirm('Delete this category? Its children will be moved to its parent.')">@csrf @method('DELETE')<x-button type="submit" color="red">Delete</x-button></form>
                        @endcan
                    </td>
                </tr>
            @empty<tr><td colspan="3" class="p-4 text-gray-500">No categories yet.</td></tr>@endforelse
            </tbody>
        </table></div>
        @can('inventory.categories.edit')
        <div id="category-top-level" class="mt-3 hidden rounded border border-dashed p-3 text-sm text-gray-600">Drop here to move to the end of the top level.</div>
        <p id="category-move-message" class="mt-2 text-sm" role="status" aria-live="polite"></p>
        @endcan
    </x-card>
</div>
@can('inventory.categories.edit')
<script>
(() => {
    let movingId = null;
    const rows = [...document.querySelectorAll('[data-category-id]')];
    const top = document.getElementById('category-top-level');
    const targets = document.querySelectorAll('.category-drop-targets');
    const token = document.querySelector('meta[name="csrf-token"]').content;
    const showTargets = (show) => {
        targets.forEach((element) => { element.classList.toggle('hidden', !show); element.classList.toggle('flex', show); });
        top.classList.toggle('hidden', !show);
    };
    const move = async (id, parentId, beforeId) => {
        const response = await fetch(@js(route('inventory.categories.move')), {
            method: 'PUT',
            credentials: 'same-origin',
            headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token},
            body: JSON.stringify({id, parent_id: parentId, before_id: beforeId, version: @js($treeVersion)})
        });
        if (response.ok) { window.location.reload(); return; }
        const payload = await response.json().catch(() => ({}));
        document.getElementById('category-move-message').textContent = Object.values(payload.errors || {}).flat().join(' ') || 'Category move failed. Refresh and try again.';
    };
    rows.forEach((row) => {
        row.addEventListener('dragstart', (event) => {
            movingId = Number(row.dataset.categoryId);
            event.dataTransfer?.setData('text/plain', String(movingId));
            showTargets(true);
        });
        row.addEventListener('dragend', () => { movingId = null; showTargets(false); });
        row.querySelector('[data-drop-before]')?.addEventListener('dragover', (event) => event.preventDefault());
        row.querySelector('[data-drop-before]')?.addEventListener('drop', (event) => {
            event.preventDefault();
            if (movingId && movingId !== Number(row.dataset.categoryId)) {
                move(movingId, row.dataset.parentId ? Number(row.dataset.parentId) : null, Number(row.dataset.categoryId));
            }
        });
        row.querySelector('[data-drop-inside]')?.addEventListener('dragover', (event) => event.preventDefault());
        row.querySelector('[data-drop-inside]')?.addEventListener('drop', (event) => {
            event.preventDefault();
            if (movingId && movingId !== Number(row.dataset.categoryId)) move(movingId, Number(row.dataset.categoryId), null);
        });
    });
    top.addEventListener('dragover', (event) => event.preventDefault());
    top.addEventListener('drop', (event) => { event.preventDefault(); if (movingId) move(movingId, null, null); });
})();
</script>
@endcan
@endcomponent
