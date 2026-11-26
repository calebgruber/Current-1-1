@php($editing = $asset !== null)
@component('layouts.app')
<div class="mx-auto max-w-4xl space-y-5">
    <h1 class="text-2xl font-bold">{{ $editing ? 'Edit asset' : 'New asset' }}</h1>
    <x-card bordered shadowless>
        <form id="asset-form" method="POST" action="{{ $editing ? route('assets.update', $asset['id']) : route('assets.store') }}" class="space-y-4">
            @csrf @if($editing)@method('PUT')@endif
            @if($errors->any())<div class="text-sm text-red-600" role="alert">{{ $errors->first() }}</div>@endif
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2" x-data="assetProductLookup()">
                    <label class="block text-sm">Serialized product <input type="search" class="mt-1 block w-full rounded border-gray-300" x-model="query" @input.debounce.300ms="lookup()" autocomplete="off" placeholder="Find a serialized product" @if($editing) disabled @endif></label>
                    <input type="hidden" name="product_id" :value="selected?.value || ''">
                    <p class="text-sm text-gray-500" x-show="selected" x-text="selected?.label"></p>
                    <ul x-show="results.length" class="mt-1 max-h-48 overflow-auto rounded border bg-white shadow">@verbatim<template x-for="option in results" :key="option.value"><li><button type="button" class="block w-full px-3 py-2 text-left hover:bg-gray-100" @click="choose(option)" x-text="option.label + (option.description ? ' · ' + option.description : '')"></button></li></template>@endverbatim</ul>
                    @if($defaultProduct)<input type="hidden" id="default-product-id" value="{{ $defaultProduct['value'] }}"><input type="hidden" id="default-product-label" value="{{ $defaultProduct['label'] }}">@endif
                </div>
                <label class="block text-sm">Asset number <x-input name="asset_number" value="{{ old('asset_number', $asset['asset_number'] ?? '') }}" :required="$editing" placeholder="Generated if blank" /></label>
                @unless($editing)<label class="block text-sm">Number of assets <x-input name="count" type="number" min="1" max="200" value="{{ old('count', 1) }}" /></label><label class="block text-sm">Asset number prefix <x-input name="prefix" maxlength="32" value="{{ old('prefix') }}" placeholder="Optional" /></label>@endunless
                <label class="block text-sm">Serial number <x-input name="serial_number" value="{{ old('serial_number', $asset['serial_number'] ?? '') }}" maxlength="128" /></label>
                <label class="block text-sm">Barcode <x-input name="barcode" value="{{ old('barcode', $asset['barcode'] ?? '') }}" maxlength="128" /></label>
                <label class="block text-sm">Condition <select name="condition" class="mt-1 block w-full rounded border-gray-300">@foreach($conditions as $option)<option value="{{ $option['value'] }}" @selected(old('condition', $asset['condition'] ?? 'good') === $option['value'])>{{ $option['label'] }}</option>@endforeach</select></label>
                <label class="block text-sm">Location <select name="location_id" class="mt-1 block w-full rounded border-gray-300"><option value="">Unassigned</option>@foreach($locations as $option)<option value="{{ $option['value'] }}" @selected(old('location_id', $asset['location_id'] ?? null) == $option['value'])>{{ $option['label'] }}</option>@endforeach</select></label>
                <label class="block text-sm">Purchase date <x-input name="purchased_at" type="date" value="{{ old('purchased_at', $asset['purchased_at'] ?? '') }}" /></label>
                <label class="block text-sm">Purchase cost <x-input name="purchase_cost" type="number" min="0" step="0.01" value="{{ old('purchase_cost', $asset['purchase_cost'] ?? '') }}" /></label>
                <label class="block text-sm">Warranty expiry <x-input name="warranty_expires_at" type="date" value="{{ old('warranty_expires_at', $asset['warranty_expires_at'] ?? '') }}" /></label>
            </div>
            <label class="block text-sm">Notes <textarea name="notes" class="mt-1 block w-full rounded border-gray-300" rows="3">{{ old('notes', $asset['notes'] ?? '') }}</textarea></label>
            @include('partials.custom-fields', ['customFields' => $customFields, 'customFieldSchema' => $customFieldSchema])
            <x-button type="submit" color="primary">Save asset</x-button>
        </form>
    </x-card>
</div>
<script>
function assetProductLookup() {
    const defaultId = document.getElementById('default-product-id')?.value;
    const defaultLabel = document.getElementById('default-product-label')?.value;
    const existing = @js($asset ? ['value' => $asset['product_id'], 'label' => $asset['product_name'] ?? 'Selected product'] : null);
    const oldProductId = @js(old('product_id'));
    const selected = existing || (oldProductId ? {value: oldProductId, label: 'Selected product'} : (defaultId ? {value: defaultId, label: defaultLabel} : null));
    return {
        query: selected?.label || '',
        selected,
        results: [],
        async lookup() {
            this.selected = null;
            if (this.query.trim().length < 1) { this.results = []; this.selected = null; return; }
            const response = await fetch('/inventory/products/lookup?tracking=serialized&q=' + encodeURIComponent(this.query.trim()), {headers: {'Accept': 'application/json'}});
            this.results = response.ok ? await response.json() : [];
        },
        choose(option) { this.selected = option; this.query = option.label; this.results = []; }
    };
}
</script>
@endcomponent
