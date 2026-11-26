@component('layouts.app')
<div class="mx-auto max-w-7xl space-y-5">
    <div class="flex flex-wrap items-end justify-between gap-4"><div><h1 class="text-2xl font-bold">Products</h1><p class="text-sm text-gray-500">Inventory catalog and stock levels.</p></div><div class="flex gap-2">@can('inventory.products.export')<x-button :href="route('inventory.products.export', request()->query())" color="secondary">Export CSV</x-button>@endcan @can('inventory.products.create')<x-button :href="route('inventory.products.create')" color="primary">Add product</x-button>@endcan</div></div>
    @if(session('success'))<x-alert color="green">{{ session('success') }}</x-alert>@endif
    @if(session('error'))<x-alert color="red">{{ session('error') }}</x-alert>@endif
    <form method="GET" action="{{ route('inventory.products.index') }}" class="grid gap-3 rounded border border-gray-200 p-4 sm:grid-cols-2 lg:grid-cols-4">
        <label class="block text-sm">Search <x-input name="search" type="search" value="{{ $filters['search'] }}" placeholder="Name, SKU, barcode, model" /></label>
        <label class="block text-sm">Category <select name="category" class="mt-1 block w-full rounded border-gray-300"><option value="">All categories</option>@foreach($categories as $option)<option value="{{ $option['value'] }}" @selected($filters['category'] == $option['value'])>{{ $option['label'] }}</option>@endforeach</select></label>
        <label class="block text-sm">Location <select name="location" class="mt-1 block w-full rounded border-gray-300"><option value="">All locations</option>@foreach($locations as $option)<option value="{{ $option['value'] }}" @selected($filters['location'] == $option['value'])>{{ $option['label'] }}</option>@endforeach</select></label>
        <label class="block text-sm">Tracking <select name="tracking" class="mt-1 block w-full rounded border-gray-300"><option value="">All types</option>@foreach($trackingOptions as $option)<option value="{{ $option['value'] }}" @selected($filters['tracking'] === $option['value'])>{{ $option['label'] }}</option>@endforeach</select></label>
        <label class="block text-sm">Tag <select name="tag" class="mt-1 block w-full rounded border-gray-300"><option value="">All tags</option>@foreach($tags as $tag)<option value="{{ $tag }}" @selected($filters['tag'] === $tag)>{{ $tag }}</option>@endforeach</select></label>
        <label class="block text-sm">Status <select name="status" class="mt-1 block w-full rounded border-gray-300">@foreach(['active' => 'Active', 'inactive' => 'Inactive', 'all' => 'All'] as $value => $label)<option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>@endforeach</select></label>
        <label class="block text-sm">Sort by <select name="sort" class="mt-1 block w-full rounded border-gray-300">@foreach(['name' => 'Name','sku' => 'SKU','category' => 'Category','tracking' => 'Tracking','replacement_cost' => 'Replacement cost','rental_price' => 'Rental price','owned' => 'Owned','created_at' => 'Created'] as $value => $label)<option value="{{ $value }}" @selected($filters['sort'] === $value)>{{ $label }}</option>@endforeach</select></label>
        <label class="block text-sm">Direction <select name="direction" class="mt-1 block w-full rounded border-gray-300"><option value="asc" @selected($filters['direction'] === 'asc')>Ascending</option><option value="desc" @selected($filters['direction'] === 'desc')>Descending</option></select></label>
        <div class="flex items-end gap-2"><x-button type="submit" color="primary">Filter products</x-button><x-button :href="route('inventory.products.index')" color="secondary">Reset</x-button></div>
    </form>
    <form id="bulk-products" method="POST" action="{{ route('inventory.products.bulk') }}" onsubmit="submitProductBulk(event)">
        @csrf
        <x-card bordered shadowless>
            @can('inventory.products.edit')
            <div class="mb-4 flex flex-wrap items-end gap-3">
                <p class="w-full text-sm text-gray-600">Select products on this page to change category or active state.</p>
                <label class="text-sm">Bulk action <select id="bulk-action" class="block rounded border-gray-300"><option value="">Choose an action</option><option value="category_id">Set category</option><option value="is_active">Set active state</option></select></label>
                <label class="text-sm">Category <select data-bulk-value="category_id" name="changes[category_id]" class="block rounded border-gray-300" disabled><option value="__choose">Choose category</option><option value="">Remove category</option>@foreach($categories as $option)<option value="{{ $option['value'] }}">{{ $option['label'] }}</option>@endforeach</select></label>
                <label class="text-sm">Active state <select data-bulk-value="is_active" name="changes[is_active]" class="block rounded border-gray-300" disabled><option value="">Choose state</option><option value="1">Active</option><option value="0">Inactive</option></select></label>
                <x-button type="submit" color="primary">Apply to selected</x-button>
                @if($errors->any())<p class="w-full text-sm text-red-600" role="alert">{{ $errors->first() }}</p>@endif<p id="bulk-errors" class="w-full text-sm text-red-600" role="alert"></p>
            </div>
            <script>
            document.getElementById('bulk-action')?.addEventListener('change', (event) => {
                document.querySelectorAll('[data-bulk-value]').forEach((input) => {
                    input.disabled = input.dataset.bulkValue !== event.target.value;
                });
            });
            async function submitProductBulk(event) {
                event.preventDefault();
                const form = event.currentTarget;
                const values = new FormData(form);
                const action = document.getElementById('bulk-action').value;
                const value = values.get(`changes[${action}]`);
                const ids = values.getAll('product_ids[]');
                const errors = document.getElementById('bulk-errors');
                errors.textContent = '';
                if (!ids.length || !action || value === null || value === '__choose' || (action === 'is_active' && value === '')) {
                    errors.textContent = 'Select products, choose an action, and choose a value.';
                    return;
                }
                if (!window.confirm(`Apply this bulk action to ${ids.length} selected product(s)?`)) return;
                const changes = action === 'category_id'
                    ? {category_id: value === '' ? null : Number(value)}
                    : {is_active: value === '1'};
                const response = await fetch(form.action, {
                    method: 'PATCH',
                    credentials: 'same-origin',
                    headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': values.get('_token')},
                    body: JSON.stringify({product_ids: ids.map(Number), changes})
                });
                if (response.ok) { window.location.reload(); return; }
                const payload = await response.json().catch(() => ({}));
                errors.textContent = Object.values(payload.errors || {}).flat().join(' ') || 'Bulk update failed.';
            }
            </script>
            @endcomponent
            @endcan
            <div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr class="border-b">
                @can('inventory.products.edit')<th class="p-3"><input type="checkbox" aria-label="Select all products on this page" onclick="document.querySelectorAll('.product-selection').forEach((box) => box.checked = this.checked)"></th>@endcan
                @foreach(['Product','SKU','Tracking','Owned','Category','Manufacturer','Status'] as $heading)<th class="p-3">{{ $heading }}</th>@endforeach<th class="p-3">Label</th>
            </tr></thead><tbody>
                @forelse($products as $product)
                <tr class="border-b"><td class="p-3">@can('inventory.products.edit')<input class="product-selection" form="bulk-products" type="checkbox" name="product_ids[]" value="{{ $product['id'] }}" aria-label="Select {{ $product['name'] }}">@endcan</td><td class="p-3"><a class="text-primary-700 underline" href="{{ route('inventory.products.show', $product['id']) }}">{{ $product['name'] }}</a></td><td class="p-3">{{ $product['sku'] ?: '—' }}</td><td class="p-3">{{ $product['tracking_label'] }}</td><td class="p-3">{{ $product['owned'] }}</td><td class="p-3">{{ $product['category']['name'] ?? '—' }}</td><td class="p-3">{{ $product['manufacturer'] ?? '—' }}</td><td class="p-3">{{ $product['is_active'] ? 'Active' : 'Inactive' }}</td><td class="p-3"><a class="text-primary-700 underline" href="{{ route('inventory.products.labels', ['ids' => [$product['id']]]) }}">Print label</a></td></tr>
                @empty<tr><td colspan="9" class="p-4 text-gray-500">No products found.</td></tr>@endforelse
            </tbody></table></div>
            <nav class="mt-4 flex items-center justify-between" aria-label="Pagination"><span>Page {{ $products->currentPage() }} of {{ $products->lastPage() }}</span><div class="flex gap-2">@if($products->previousPageUrl())<x-button :href="$products->previousPageUrl()" color="secondary">Previous</x-button>@endif @if($products->nextPageUrl())<x-button :href="$products->nextPageUrl()" color="secondary">Next</x-button>@endif</div></nav>
        </x-card>
    </form>
</div>
