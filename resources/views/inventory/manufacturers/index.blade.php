@component('layouts.app')
<div class="mx-auto max-w-7xl space-y-5">
    <div class="flex flex-wrap items-end justify-between gap-4"><div><h1 class="text-2xl font-bold">Manufacturers</h1><p class="text-sm text-gray-500">Manage product manufacturers.</p></div><x-button :href="route('inventory.categories.index')" color="secondary">Categories</x-button></div>
    @if(session('success'))<x-alert color="green">{{ session('success') }}</x-alert>@endif
    @if(session('error'))<x-alert color="red">{{ session('error') }}</x-alert>@endif
    @can('inventory.categories.create')<x-card bordered shadowless header="Add manufacturer">
        <form method="POST" action="{{ route('inventory.manufacturers.store') }}" class="flex flex-wrap items-end gap-3">@csrf
            <label class="block text-sm">Name <x-input name="name" value="{{ old('name') }}" required maxlength="255" /></label>
            <label class="block text-sm">Website <x-input name="website" type="url" value="{{ old('website') }}" maxlength="255" /></label>
            <x-button type="submit" color="primary">Add manufacturer</x-button>
        </form>
        @if($errors->any())<p class="mt-2 text-sm text-red-600" role="alert">{{ $errors->first() }}</p>@endif
    </x-card>@endcan
    <form method="GET" action="{{ route('inventory.manufacturers.index') }}" class="flex gap-2"><label class="sr-only" for="manufacturer-search">Search manufacturers</label><x-input id="manufacturer-search" name="search" type="search" value="{{ $filters['search'] }}" placeholder="Search manufacturers" /><x-button type="submit" color="secondary">Search</x-button></form>
    <x-card bordered shadowless><div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr class="border-b"><th class="p-3">Name</th><th class="p-3">Website</th><th class="p-3">Products</th><th class="p-3">Actions</th></tr></thead><tbody>
        @forelse($manufacturers as $manufacturer)
        <tr class="border-b align-top"><td class="p-3">{{ $manufacturer['name'] }}</td><td class="p-3">{{ $manufacturer['website'] ?: '—' }}</td><td class="p-3">{{ $manufacturer['products_count'] }}</td><td class="p-3">
            @can('inventory.categories.edit')<details><summary class="cursor-pointer text-primary-700">Edit</summary><form method="POST" action="{{ route('inventory.manufacturers.update', $manufacturer['id']) }}" class="mt-2 flex flex-wrap items-end gap-2">@csrf @method('PUT')<label>Name <x-input name="name" value="{{ $manufacturer['name'] }}" required maxlength="255" /></label><label>Website <x-input name="website" type="url" value="{{ $manufacturer['website'] }}" maxlength="255" /></label><x-button type="submit" color="primary">Save</x-button></form></details>@endcan
            @can('inventory.categories.delete')<form method="POST" action="{{ route('inventory.manufacturers.destroy', $manufacturer['id']) }}" onsubmit="return confirm('Delete this manufacturer?')">@csrf @method('DELETE')<x-button type="submit" color="red">Delete</x-button></form>@endcan
        </td></tr>
        @empty<tr><td colspan="4" class="p-4 text-gray-500">No manufacturers found.</td></tr>@endforelse
    </tbody></table></div>
        @if($manufacturers->hasPages())<nav class="mt-4 flex gap-2" aria-label="Pagination">@if($manufacturers->previousPageUrl())<x-button :href="$manufacturers->previousPageUrl()" color="secondary">Previous</x-button>@endif@if($manufacturers->nextPageUrl())<x-button :href="$manufacturers->nextPageUrl()" color="secondary">Next</x-button>@endif</nav>@endif
    </x-card>
</div>
@endcomponent
