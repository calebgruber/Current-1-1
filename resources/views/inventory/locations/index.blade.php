@component('layouts.app')
<div class="mx-auto max-w-7xl space-y-5">
    <div class="flex flex-wrap items-end justify-between gap-4"><div><h1 class="text-2xl font-bold">Locations</h1><p class="text-sm text-gray-500">Manage the locations where equipment is stored.</p></div>@can('inventory.locations.create')<x-button :href="route('inventory.locations.create')" color="primary">Add location</x-button>@endcan</div>
    <form method="GET" action="{{ route('inventory.locations.index') }}" class="flex flex-wrap gap-2">
        <label class="sr-only" for="location-search">Search locations</label><x-input id="location-search" name="search" type="search" value="{{ $filters['search'] }}" placeholder="Search locations" />
        <label class="sr-only" for="location-type">Location type</label><select id="location-type" name="type" class="rounded border-gray-300"><option value="">All types</option>@foreach($types as $option)<option value="{{ $option['value'] }}" @selected($filters['type'] === $option['value'])>{{ $option['label'] }}</option>@endforeach</select><x-button type="submit" color="secondary">Search</x-button>
    </form>
    <x-card bordered shadowless><div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr class="border-b">@foreach(['Location','Type','Code','Barcode','Status','Sub-locations','Assets','Bulk units','Products'] as $heading)<th class="p-3">{{ $heading }}</th>@endforeach @foreach($customFieldColumns as $field)<th class="p-3">{{ $field['label'] }}</th>@endforeach</tr></thead><tbody>
        @forelse($locations as $location)
        <tr class="border-b"><td class="p-3"><a class="text-primary-700 underline" href="{{ route('inventory.locations.show', $location['id']) }}">{{ $location['path'] }}</a></td><td class="p-3 capitalize">{{ $location['type'] }}</td><td class="p-3">{{ $location['code'] ?: '—' }}</td><td class="p-3">{{ $location['barcode'] ?: '—' }}</td><td class="p-3">{{ $location['is_active'] ? 'Active' : 'Inactive' }}</td><td class="p-3">{{ $location['children_count'] }}</td><td class="p-3">{{ $location['assets_count'] }}</td><td class="p-3">{{ $location['bulk_units'] }}</td><td class="p-3">{{ $location['products_count'] }}</td>@foreach($customFieldColumns as $field)@php($customValue = data_get($location['custom_fields'], $field['key']))<td class="p-3">{{ is_array($customValue) ? implode(', ', $customValue) : ($customValue ?? '—') }}</td>@endforeach</tr>
        @empty<tr><td colspan="{{ 9 + count($customFieldColumns) }}" class="p-4 text-gray-500">No locations found.</td></tr>@endforelse
    </tbody></table></div></x-card>
</div>
@endcomponent
