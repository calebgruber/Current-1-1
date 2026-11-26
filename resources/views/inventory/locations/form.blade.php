@php($editing = $location !== null)
@component('layouts.app')
<div class="mx-auto max-w-4xl space-y-5">
    <h1 class="text-2xl font-bold">{{ $editing ? 'Edit location' : 'New location' }}</h1>
    <x-card bordered shadowless>
        <form method="POST" action="{{ $editing ? route('inventory.locations.update', $location['id']) : route('inventory.locations.store') }}" class="space-y-4">
            @csrf @if($editing)@method('PUT')@endif
            @if($errors->any())<div class="text-sm text-red-600" role="alert">{{ $errors->first() }}</div>@endif
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="block text-sm">Name <x-input name="name" value="{{ old('name', $location['name'] ?? '') }}" maxlength="255" required /></label>
                <label class="block text-sm">Parent location <select name="parent_id" class="mt-1 block w-full rounded border-gray-300"><option value="">None</option>@foreach($parentOptions as $option)<option value="{{ $option['value'] }}" @selected(old('parent_id', $location['parent_id'] ?? $defaultParentId) == $option['value'])>{{ $option['label'] }}</option>@endforeach</select></label>
                <label class="block text-sm">Type <select name="type" class="mt-1 block w-full rounded border-gray-300" required>@foreach($types as $option)<option value="{{ $option['value'] }}" @selected(old('type', $location['type'] ?? 'warehouse') === $option['value'])>{{ $option['label'] }}</option>@endforeach</select></label>
                <label class="block text-sm">Code <x-input name="code" value="{{ old('code', $location['code'] ?? '') }}" maxlength="64" /></label>
                <label class="block text-sm">Barcode <x-input name="barcode" value="{{ old('barcode', $location['barcode'] ?? '') }}" maxlength="128" /></label>
            </div>
            @endcomponent
            <label class="block text-sm">Address <textarea name="address" class="mt-1 block w-full rounded border-gray-300" rows="3" maxlength="1000">{{ old('address', $location['address'] ?? '') }}</textarea></label>
            <label class="block text-sm">Notes <textarea name="notes" class="mt-1 block w-full rounded border-gray-300" rows="3" maxlength="5000">{{ old('notes', $location['notes'] ?? '') }}</textarea></label>
            <input type="hidden" name="is_active" value="0"><label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $location['is_active'] ?? true))> Active</label>
            @include('partials.custom-fields', ['customFields' => $customFields, 'customFieldSchema' => $customFieldSchema])
            <div class="flex gap-2"><x-button type="submit" color="primary">Save location</x-button><x-button :href="$editing ? route('inventory.locations.show', $location['id']) : route('inventory.locations.index')" color="secondary">Cancel</x-button></div>
        </form>
    </x-card>
</div>
