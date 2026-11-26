@php($editing = $record !== null)
@php($selectedProduct = $record ? $products->firstWhere('id', $record->product_id) : null)
@component('layouts.app')
<div class="mx-auto max-w-4xl space-y-5">
    <h1 class="text-2xl font-bold">{{ $editing ? 'Edit maintenance record' : 'New maintenance record' }}</h1>
    <x-card bordered shadowless>
        <form id="maintenance-form" method="POST" action="{{ $editing ? route('maintenance.update', $record) : route('maintenance.store') }}" class="space-y-4">
            @csrf @if($editing)@method('PUT')@endif
            @if($errors->any())<div class="text-sm text-red-600" role="alert">{{ $errors->first() }}</div>@endif
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="block text-sm">Product <select id="maintenance-product" name="product_id" class="mt-1 block w-full rounded border-gray-300" required @if($editing) disabled @endif>
                    <option value="">Select product</option>@foreach($products as $option)<option value="{{ $option->id }}" data-tracking="{{ $option->tracking->value }}" data-deleted="{{ $option->deleted_at ? '1' : '0' }}" @selected(old('product_id', $record?->product_id) == $option->id)>{{ $option->name }}{{ $option->deleted_at ? ' (deleted)' : '' }}</option>@endforeach
                </select></label>
                @if($editing)<input type="hidden" name="product_id" value="{{ $record->product_id }}">@endif
                <label id="maintenance-asset-field" class="block text-sm">Asset <select id="maintenance-asset" name="asset_id" class="mt-1 block w-full rounded border-gray-300" @if($editing) disabled @endif>
                    <option value="">Select asset</option>@foreach($assets as $option)<option value="{{ $option->id }}" data-product="{{ $option->product_id }}" data-deleted="{{ $option->deleted_at ? '1' : '0' }}" @selected(old('asset_id', $record?->asset_id) == $option->id)>{{ $option->asset_number }}{{ $option->deleted_at ? ' (deleted)' : '' }}</option>@endforeach
                </select>@if($editing)<input type="hidden" name="asset_id" value="{{ $record->asset_id }}">@endif</label>
                <div id="maintenance-scanner" class="sm:col-span-2 space-y-2" @if($editing) hidden @endif>
                    <label class="block text-sm">Scan or enter asset barcode, serial number, or asset number <input id="maintenance-scan-code" class="mt-1 block w-full rounded border-gray-300" maxlength="128" autocomplete="off"></label>
                    <x-button type="button" id="maintenance-find-asset" color="secondary">Find asset</x-button>
                    <p class="text-sm text-gray-500">Codes are case-sensitive and searched within the selected serialized product. Press Enter or choose Find asset.</p>
                    <p id="maintenance-scan-message" class="text-sm" role="status" aria-live="polite"></p>
                </div>
                <label class="block text-sm">Quantity <x-input id="maintenance-quantity" name="quantity" type="number" min="1" max="{{ $selectedProduct?->tracking?->value === 'serialized' ? 1 : 10000000 }}" value="{{ old('quantity', $record?->quantity ?? 1) }}" :disabled="$editing" required /></label>
                @if($editing)<input type="hidden" name="quantity" value="{{ $record->quantity }}">@endif
                <label class="block text-sm">Title <x-input name="title" value="{{ old('title', $record?->title) }}" maxlength="255" required /></label>
                <label class="block text-sm">Type <select name="type" class="mt-1 block w-full rounded border-gray-300">@foreach($types as $type)<option value="{{ $type }}" @selected(old('type', $record?->type ?? 'repair') === $type)>{{ str_replace('_', ' ', $type) }}</option>@endforeach</select></label>
                <label class="block text-sm">Status <select name="status" class="mt-1 block w-full rounded border-gray-300">@foreach($statuses as $status)@if(auth()->user()->can('maintenance.records.approve') || !in_array($status['value'], ['completed','written_off'], true) || $status['value'] === $record?->status?->value)<option value="{{ $status['value'] }}" @selected(old('status', $record?->status?->value ?? 'open') === $status['value'])>{{ $status['label'] }}</option>@endif @endforeach</select></label>
                <label class="block text-sm">Priority <select name="priority" class="mt-1 block w-full rounded border-gray-300">@foreach($priorities as $priority)<option value="{{ $priority }}" @selected(old('priority', $record?->priority ?? 'normal') === $priority)>{{ ucfirst($priority) }}</option>@endforeach</select></label>
                <label class="block text-sm">Assigned to <select name="assigned_to" class="mt-1 block w-full rounded border-gray-300"><option value="">Unassigned</option>@foreach($users as $user)<option value="{{ $user->id }}" @selected(old('assigned_to', $record?->assigned_to) == $user->id)>{{ $user->name }}</option>@endforeach</select></label>
                <label class="block text-sm">Job (optional) <select name="job_id" class="mt-1 block w-full rounded border-gray-300"><option value="">None</option>@foreach($jobs as $job)<option value="{{ $job->id }}" @selected(old('job_id', $record?->job_id) == $job->id)>{{ $job->number }} — {{ $job->name }}{{ $job->deleted_at ? ' (deleted)' : '' }}</option>@endforeach</select></label>
                <label class="block text-sm">Due date <input id="maintenance-due-local" type="datetime-local" class="mt-1 block w-full rounded border-gray-300" value="{{ old('due_at') }}"></label>
                <input type="hidden" name="due_at" id="maintenance-due-utc" value="{{ old('due_at') }}">
                <label class="block text-sm">Cost <x-input name="cost" type="number" min="0" step="0.01" value="{{ old('cost', $record?->cost) }}" /></label>
            </div>
            <label class="block text-sm">Description <textarea name="description" rows="3" class="mt-1 block w-full rounded border-gray-300">{{ old('description', $record?->description) }}</textarea></label>
            <label class="block text-sm">Resolution <textarea name="resolution" rows="3" class="mt-1 block w-full rounded border-gray-300">{{ old('resolution', $record?->resolution) }}</textarea></label>
            <p id="maintenance-bulk-note" class="text-sm text-gray-500" hidden>This record does not make bulk stock unavailable. Adjust stock quantities separately in inventory.</p>
            <p id="maintenance-deleted-note" class="text-sm text-gray-500" hidden>Deleted items can be closed but cannot be reopened. A deleted asset's status is not changed.</p>
            <x-button type="submit" id="maintenance-save" color="primary">Save record</x-button>
        </form>
    </x-card>
</div>
<script>
(() => {
    const form = document.getElementById('maintenance-form');
    const product = document.getElementById('maintenance-product');
    const asset = document.getElementById('maintenance-asset');
    const scanner = document.getElementById('maintenance-scanner');
    const code = document.getElementById('maintenance-scan-code');
    const message = document.getElementById('maintenance-scan-message');
    const button = document.getElementById('maintenance-find-asset');
    const quantity = document.getElementById('maintenance-quantity');
    const save = document.getElementById('maintenance-save');
    const dueLocal = document.getElementById('maintenance-due-local');
    const dueUtc = document.getElementById('maintenance-due-utc');
    const bulkNote = document.getElementById('maintenance-bulk-note');
    const deletedNote = document.getElementById('maintenance-deleted-note');
    const editing = @js($editing);
    let revision = 0;
    let scanning = false;
    const selectedProduct = () => product.options[product.selectedIndex];
    const updateProduct = (clear = true) => {
        const selected = selectedProduct();
        const serialized = selected?.dataset.tracking === 'serialized';
        scanner.hidden = !serialized || editing;
        document.getElementById('maintenance-asset-field').hidden = !serialized;
        quantity.max = serialized ? '1' : '10000000';
        if (serialized) quantity.value = '1';
        if (clear && !editing) asset.value = '';
        for (const option of asset.options) {
            if (!option.value) continue;
            option.hidden = option.dataset.product !== selected?.value;
        }
        bulkNote.hidden = serialized || !selected?.value;
        const selectedAsset = asset.options[asset.selectedIndex];
        deletedNote.hidden = !(selected?.dataset.deleted === '1' || selectedAsset?.dataset.deleted === '1');
    };
    const cancelLookup = () => { revision++; scanning = false; button.disabled = false; button.textContent = 'Find asset'; message.textContent = ''; };
    const lookup = async () => {
        cancelLookup();
        asset.value = '';
        const value = code.value.trim();
        const productId = product.value;
        if (!value || !productId || selectedProduct()?.dataset.tracking !== 'serialized') {
            message.textContent = 'Select a serialized product and enter a code.';
            return;
        }
        const current = revision;
        scanning = true;
        button.disabled = true;
        button.textContent = 'Finding…';
        try {
            const response = await fetch(@js(route('maintenance.asset-lookup')), {
                method: 'POST',
                credentials: 'same-origin',
                headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content},
                body: JSON.stringify({product_id: Number(productId), code: value})
            });
            const data = await response.json();
            if (current !== revision) return;
            if (!response.ok) throw new Error(data.message || 'Lookup failed. Please try again.');
            let option = [...asset.options].find((item) => item.value === String(data.asset.id));
            if (!option) { option = new Option(data.asset.asset_number, data.asset.id); option.dataset.product = productId; asset.add(option); }
            option.hidden = false;
            asset.value = String(data.asset.id);
            quantity.value = '1';
            message.textContent = `Selected asset ${data.asset.asset_number}.`;
            updateProduct(false);
        } catch (error) {
            if (current === revision) message.textContent = error.message || 'Lookup failed. Please try again.';
        } finally {
            if (current === revision) { scanning = false; button.disabled = false; button.textContent = 'Find asset'; }
        }
    };
    product.addEventListener('change', () => { cancelLookup(); code.value = ''; updateProduct(true); });
    code.addEventListener('input', () => { cancelLookup(); asset.value = ''; });
    code.addEventListener('keydown', (event) => { if (event.key === 'Enter') { event.preventDefault(); lookup(); } });
    button.addEventListener('click', lookup);
    asset.addEventListener('change', () => { cancelLookup(); updateProduct(false); });
    form.addEventListener('submit', (event) => {
        if (scanning) { event.preventDefault(); return; }
        dueUtc.value = dueLocal.value ? new Date(dueLocal.value).toISOString() : '';
    });
    const dueAt = dueUtc.value || @js($record?->due_at?->toIso8601String());
    if (!dueLocal.value && dueAt) {
        const date = new Date(dueAt);
        dueLocal.value = new Date(date.getTime() - date.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
    }
    updateProduct(false);
})();
</script>
@endcomponent
