@php($editing = $product !== null)
@component('layouts.app')
<div class="mx-auto max-w-4xl space-y-5">
    <h1 class="text-2xl font-bold">{{ $editing ? 'Edit product' : 'New product' }}</h1>
    <x-card bordered shadowless>
        <form method="POST" action="{{ $editing ? route('inventory.products.update', $product['id']) : route('inventory.products.store') }}" class="space-y-5" x-data="productRelationships()" x-on:submit="prepareRelationships($el)">
            @csrf @if($editing)@method('PUT')@endif
            @if($errors->any())<div class="text-sm text-red-600" role="alert">{{ $errors->first() }}</div>@endif
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="block text-sm">Name <x-input name="name" value="{{ old('name', $product['name'] ?? '') }}" required maxlength="255" /></label>
                <label class="block text-sm">SKU <x-input name="sku" value="{{ old('sku', $product['sku'] ?? '') }}" maxlength="64" /></label>
                <label class="block text-sm">Barcode <x-input name="barcode" value="{{ old('barcode', $product['barcode'] ?? '') }}" maxlength="128" /></label>
                <label class="block text-sm">Model <x-input name="model" value="{{ old('model', $product['model'] ?? '') }}" maxlength="255" /></label>
                <label class="block text-sm">Category <select name="category_id" class="mt-1 block w-full rounded border-gray-300"><option value="">None</option>@foreach($categories as $option)<option value="{{ $option['value'] }}" @selected(old('category_id', $product['category_id'] ?? null) == $option['value'])>{{ $option['label'] }}</option>@endforeach</select></label>
                <label class="block text-sm">Manufacturer <select name="manufacturer_id" class="mt-1 block w-full rounded border-gray-300"><option value="">None</option>@foreach($manufacturers as $option)<option value="{{ $option['value'] }}" @selected(old('manufacturer_id', $product['manufacturer_id'] ?? null) == $option['value'])>{{ $option['label'] }}</option>@endforeach</select></label>
                @can('inventory.categories.create')<label class="block text-sm">Or create manufacturer <x-input name="manufacturer_name" value="{{ old('manufacturer_name', $product['manufacturer_name'] ?? '') }}" maxlength="255" /></label>@endcan
                <label class="block text-sm">Default location <select name="default_location_id" class="mt-1 block w-full rounded border-gray-300"><option value="">None</option>@foreach($locations as $option)<option value="{{ $option['value'] }}" @selected(old('default_location_id', $product['default_location_id'] ?? null) == $option['value'])>{{ $option['label'] }}</option>@endforeach</select></label>
                <label class="block text-sm">Replacement cost <x-input name="replacement_cost" type="number" min="0" step="0.01" value="{{ old('replacement_cost', $product['replacement_cost'] ?? '') }}" /></label>
                <label class="block text-sm">Rental price <x-input name="rental_price" type="number" min="0" step="0.01" value="{{ old('rental_price', $product['rental_price'] ?? '') }}" /></label>
                <label class="block text-sm">Weight (kg) <x-input name="weight_kg" type="number" min="0" step="0.001" value="{{ old('weight_kg', $product['weight_kg'] ?? '') }}" /></label>
            </div>
            @if($editing)<input type="hidden" name="tracking" value="{{ $product['tracking'] }}">@endif
            <fieldset class="space-y-2" @if($editing) disabled @endif><legend class="font-semibold">Tracking</legend>@foreach($trackingOptions as $option)<label class="flex items-center gap-2"><input type="radio" name="tracking" value="{{ $option['value'] }}" @checked(old('tracking', $product['tracking'] ?? 'bulk') === $option['value'])> {{ $option['label'] }}</label>@endforeach<p class="text-sm text-gray-500">Bulk tracks quantities by location; serialized tracks individual assets and serial numbers. Tracking cannot be changed after creation.</p></fieldset>
            <label class="block text-sm">Description <textarea name="description" rows="3" class="mt-1 block w-full rounded border-gray-300">{{ old('description', $product['description'] ?? '') }}</textarea></label>
            <input type="hidden" name="is_active" value="0"><label class="flex items-center gap-2"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $product['is_active'] ?? true))> Active</label>
            <section class="space-y-3"><h2 class="font-semibold">Tags</h2><template x-for="(tag, index) in tags" :key="index"><div class="flex items-center gap-2"><span x-text="tag" class="rounded border px-2 py-1"></span><input type="hidden" name="tags[]" :value="tag"><button type="button" class="text-red-700 underline" @click="tags.splice(index, 1)" :aria-label="'Remove tag ' + tag">Remove</button></div></template><div class="flex gap-2"><input x-model="tagInput" list="known-tags" class="rounded border-gray-300" maxlength="50" aria-label="Add a tag" @keydown.enter.prevent="addTag()"><datalist id="known-tags">@foreach($tags as $tag)<option value="{{ $tag }}">@endforeach</datalist><x-button type="button" color="secondary" @click="addTag()">Add tag</x-button></div></section>
            @can('inventory.products.view')
            <section class="space-y-3"><div><h2 class="font-semibold">Accessories</h2><p class="text-sm text-gray-500">Quantity is per parent unit; auto-add controls inclusion when accessories are requested.</p></div>
                <template x-for="(row, index) in accessories" :key="row.key"><div class="space-y-2 rounded border p-3">
                    <label class="block text-sm">Accessory product
                        <input type="hidden" :name="'accessories[' + index + '][accessory_product_id]'" :value="row.accessory_product_id" required>
                        <input type="search" class="mt-1 block w-full rounded border-gray-300" x-model="row.search" @input.debounce.300ms="lookup(row)" autocomplete="off" placeholder="Find a product">
                        <ul x-show="row.matches.length" class="mt-1 max-h-40 overflow-auto rounded border bg-white shadow">@verbatim<template x-for="option in row.matches" :key="option.value"><li><button type="button" class="block w-full px-3 py-2 text-left hover:bg-gray-100" @click="choose(row, option)" x-text="option.label + (option.description ? ' · ' + option.description : '')"></button></li></template>@endverbatim</ul>
                    </label>
                    <div class="grid gap-2 sm:grid-cols-2"><label class="block text-sm">Quantity per parent <x-input type="number" min="0.01" max="10000" step="0.01" x-bind:name="'accessories[' + index + '][quantity]'" x-model="row.quantity" required /></label><label class="block text-sm">Type <select class="mt-1 block w-full rounded border-gray-300" :name="'accessories[' + index + '][type]'" x-model="row.type">@foreach($accessoryTypes as $type)<option value="{{ $type['value'] }}">{{ $type['label'] }}</option>@endforeach</select></label></div>
                    <label class="block text-sm">Notes <x-input maxlength="255" x-bind:name="'accessories[' + index + '][notes]'" x-model="row.notes" /></label>
                    <input type="hidden" :name="'accessories[' + index + '][auto_add]'" value="0"><label class="flex items-center gap-2"><input type="checkbox" value="1" :name="'accessories[' + index + '][auto_add]'" x-model="row.auto_add"> Auto-add</label>
                    <x-button type="button" color="red" @click="accessories.splice(index, 1)">Remove accessory</x-button>
                </div></template>
                <x-button type="button" color="secondary" @click="addAccessory()">Add accessory</x-button>
            </section>
            <section class="space-y-3"><div><h2 class="font-semibold">Alternative products</h2><p class="text-sm text-gray-500">Suggestions are not reciprocal and are never substituted automatically.</p></div>
                <template x-for="(row, index) in alternatives" :key="row.key"><div class="flex items-end gap-2"><label class="block flex-1 text-sm">Alternative product
                    <input type="hidden" :name="'alternatives[' + index + '][product_id]'" :value="row.product_id" required>
                    <input type="search" class="mt-1 block w-full rounded border-gray-300" x-model="row.search" @input.debounce.300ms="lookup(row)" autocomplete="off" placeholder="Find a product">
                    <ul x-show="row.matches.length" class="mt-1 max-h-40 overflow-auto rounded border bg-white shadow">@verbatim<template x-for="option in row.matches" :key="option.value"><li><button type="button" class="block w-full px-3 py-2 text-left hover:bg-gray-100" @click="choose(row, option)" x-text="option.label + (option.description ? ' · ' + option.description : '')"></button></li></template>@endverbatim</ul>
                </label><x-button type="button" color="red" @click="alternatives.splice(index, 1)">Remove</x-button></div></template>
                <x-button type="button" color="secondary" @click="addAlternative()">Add alternative</x-button>
            </section>
            @else<p class="text-sm text-gray-500">Product view permission is required to edit accessories and alternatives.</p>@endcan
            @include('partials.custom-fields', ['customFields' => $customFields, 'customFieldSchema' => $customFieldSchema])
            <x-button type="submit" color="primary">Save product</x-button>
        </form>
    </x-card>
</div>
<script>
function productRelationships() {
    const makeKey = () => Math.random().toString(36).slice(2);
    const same = (left, right) => JSON.stringify(left.slice().sort()) === JSON.stringify(right.slice().sort());
    return {
        editing: @js($editing),
        tagInput: '',
        tags: @js(old('tags', $product['tags'] ?? [])),
        accessories: (@js(old('accessories', $product['accessories'] ?? []))).map((row) => ({...row, key: makeKey(), search: row.name || '', matches: [], auto_add: !!row.auto_add})),
        alternatives: (@js(old('alternatives', $product['alternatives'] ?? []))).map((row) => ({...row, key: makeKey(), search: row.name || '', matches: []})),
        initialTags: @js($product['tags'] ?? []),
        initialAccessories: @js($product['accessories'] ?? []),
        initialAlternatives: @js($product['alternatives'] ?? []),
        addTag() { const value = this.tagInput.trim(); if (value && !this.tags.some((tag) => tag.toLowerCase() === value.toLowerCase())) this.tags.push(value); this.tagInput = ''; },
        addAccessory() { this.accessories.push({ key: makeKey(), accessory_product_id: '', quantity: 1, type: 'required', auto_add: true, notes: '', search: '', matches: [] }); },
        addAlternative() { this.alternatives.push({ key: makeKey(), product_id: '', search: '', matches: [] }); },
        async lookup(row) {
            row.accessory_product_id = null;
            row.product_id = null;
            if (row.search.trim().length < 1) { row.matches = []; return; }
            const response = await fetch('/inventory/products/lookup?q=' + encodeURIComponent(row.search.trim()), {headers: {'Accept': 'application/json'}});
            row.matches = response.ok ? await response.json() : [];
        },
        choose(row, option) {
            row.accessory_product_id = option.value;
            row.product_id = option.value;
            row.name = option.label;
            row.search = option.label;
            row.matches = [];
        },
        prepareRelationships(form) {
            if (!this.editing) return;
            const tags = this.tags.map((tag) => JSON.stringify(tag)).sort();
            const originalTags = this.initialTags.map((tag) => JSON.stringify(tag)).sort();
            const accessories = this.accessories.map((row) => JSON.stringify({
                id: row.accessory_product_id === '' || row.accessory_product_id === null ? null : String(row.accessory_product_id),
                quantity: Number(row.quantity), type: row.type, auto_add: !!row.auto_add, notes: row.notes || null
            })).sort();
            const originalAccessories = this.initialAccessories.map((row) => JSON.stringify({
                id: row.accessory_product_id === null ? null : String(row.accessory_product_id),
                quantity: Number(row.quantity), type: row.type, auto_add: !!row.auto_add, notes: row.notes || null
            })).sort();
            const alternatives = this.alternatives.map((row) => JSON.stringify(row.product_id === '' || row.product_id === null ? null : String(row.product_id))).sort();
            const originalAlternatives = this.initialAlternatives.map((row) => JSON.stringify(row.product_id === null ? null : String(row.product_id))).sort();
            const unchanged = [
                ['tags', same(tags, originalTags), this.tags.length === 0],
                ['accessories', same(accessories, originalAccessories), this.accessories.length === 0],
                ['alternatives', same(alternatives, originalAlternatives), this.alternatives.length === 0]
            ];
            for (const [name, isUnchanged, empty] of unchanged) {
                if (isUnchanged) form.querySelectorAll(`[name="${name}[]"], [name^="${name}["], [name="${name}"]`).forEach((input) => input.removeAttribute('name'));
                else if (empty) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = name;
                    input.value = '';
                    form.append(input);
                }
            }
        }
    };
}
</script>
@endcomponent
