@component('layouts.app')
@php
    $stageColors = match ($job['stage']) {
        'approved', 'reserved', 'ready' => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
        'quote', 'prep' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
        'checked_out', 'returned' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
        default => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
    };
@endphp
<div class="mx-auto max-w-7xl space-y-4">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <a href="{{ route('jobs.index') }}" class="text-xs font-medium text-accent hover:underline">Jobs</a>
            <div class="mt-1 flex flex-wrap items-center gap-2">
                <h1 class="text-xl font-bold tracking-tight text-ink">{{ $job['number'] }} · {{ $job['name'] }}</h1>
                <span class="rounded-md px-2 py-0.5 text-xs font-semibold {{ $stageColors }}">{{ $job['stage_label'] }}</span>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-2 text-sm">
            <a class="st-link" href="{{ route('jobs.pull-sheet', $job['id']) }}">Pull sheet</a>
            @if($can['edit'])<a class="st-link" href="{{ route('jobs.edit', $job['id']) }}">Edit</a>@endif
            @if($can['delete'])<form method="POST" action="{{ route('jobs.destroy', $job['id']) }}" onsubmit="return confirm('Delete this job?')">@csrf @method('DELETE')<button class="rounded-md border border-red-200 px-2 py-1 font-medium text-red-700 hover:bg-red-50 dark:border-red-900 dark:text-red-400 dark:hover:bg-red-950" type="submit">Delete</button></form>@endif
        </div>
    </div>
    @if(session('success'))<x-alert color="green">{{ session('success') }}</x-alert>@endif
    @if(session('error'))<x-alert color="red">{{ session('error') }}</x-alert>@endif
    <div class="grid gap-4 lg:grid-cols-2">
        <section class="st-card overflow-hidden" aria-labelledby="job-details-heading">
            <h2 id="job-details-heading" class="border-b border-line bg-subtle px-4 py-2 text-sm font-semibold">Job details</h2>
            <div class="space-y-3 p-4">
                <dl class="grid gap-3 sm:grid-cols-2">
                    <div><dt class="text-xs font-medium uppercase tracking-wide text-muted">Start</dt><dd class="mt-1">@if($job['starts_at'])<time data-format="datetime" datetime="{{ $job['starts_at'] }}">{{ \Illuminate\Support\Carbon::parse($job['starts_at'])->format('M j, Y g:i A') }}</time>@else—@endif</dd></div>
                    <div><dt class="text-xs font-medium uppercase tracking-wide text-muted">Return</dt><dd class="mt-1">@if($job['returns_at'])<time data-format="datetime" datetime="{{ $job['returns_at'] }}">{{ \Illuminate\Support\Carbon::parse($job['returns_at'])->format('M j, Y g:i A') }}</time>@else—@endif</dd></div>
                </dl>
                @if($job['description'])<div><h3 class="text-xs font-medium uppercase tracking-wide text-muted">Description</h3><p class="mt-1 whitespace-pre-wrap">{{ $job['description'] }}</p></div>@endif
                @if($contacts->isNotEmpty())<div><h3 class="text-xs font-medium uppercase tracking-wide text-muted">Contacts</h3><ul class="mt-1 space-y-1">@foreach($contacts as $contact)<li>{{ $contact['name'] }}{{ $contact['role'] ? ' · '.$contact['role'] : '' }}{{ $contact['email'] ? ' · '.$contact['email'] : '' }}</li>@endforeach</ul></div>@endif
            </div>
        </section>
        @if($customFieldSchema || (auth()->user()->can('settings.custom_fields.view') && \Illuminate\Support\Facades\Route::has('settings.custom-fields.index')))
            <section class="st-card overflow-hidden" aria-labelledby="custom-fields-heading">
                <div class="flex items-center justify-between gap-2 border-b border-line bg-subtle px-4 py-2"><h2 id="custom-fields-heading" class="text-sm font-semibold">Custom fields</h2>@if(auth()->user()->can('settings.custom_fields.view') && \Illuminate\Support\Facades\Route::has('settings.custom-fields.index'))<a class="text-xs font-medium text-accent hover:underline" href="{{ route('settings.custom-fields.index') }}">Manage fields</a>@endif</div>
                <div class="p-4">@include('partials.custom-field-display', ['customFieldSchema' => $customFieldSchema, 'customFields' => $customFields])
                @unless($customFieldSchema)<p class="text-sm text-muted">No custom fields configured for jobs.</p>@endunless</div>
            </section>
        @endif
    </div>
    @if(collect($job['allowed_transitions'])->contains(fn ($transition) => $transition['allowed']))
        <section class="st-card overflow-hidden" aria-labelledby="move-stage-heading">
            <h2 id="move-stage-heading" class="border-b border-line bg-subtle px-4 py-2 text-sm font-semibold">Move stage</h2>
            <form method="POST" action="{{ route('jobs.transition', $job['id']) }}" class="flex flex-wrap items-end gap-3 p-4">
                @csrf
                <label class="block text-sm">New stage <select name="stage" required class="mt-1 block w-full rounded-md border border-line bg-surface px-3 py-2 text-ink"><option value="">Choose stage</option>@foreach($job['allowed_transitions'] as $transition)@if($transition['allowed'])<option value="{{ $transition['value'] }}" @selected(old('stage') === $transition['value'])>{{ $transition['label'] }}</option>@endif @endforeach</select></label>
                <label class="block text-sm">Notes <x-input name="notes" value="{{ old('notes') }}" maxlength="1000" placeholder="Notes (optional)" /></label>
                @if($can['override'])<div class="space-y-2"><label class="flex items-center gap-2"><input id="stage-override" type="checkbox" name="override" value="1" @checked(old('override'))> Override overbooking</label><label id="stage-override-reason" class="{{ old('override') ? '' : 'hidden' }}">Override reason <x-input name="override_reason" value="{{ old('override_reason') }}" minlength="5" maxlength="500" placeholder="Override reason" /></label></div>@endif
                <x-button type="submit" color="primary">Move</x-button>
                @error('stage')<span role="alert" class="text-sm text-red-600">{{ $message }}</span>@enderror
                @error('override_reason')<span role="alert" class="text-sm text-red-600">{{ $message }}</span>@enderror
            </form>
        </section>
    @endif
    <section class="st-card overflow-hidden" aria-labelledby="equipment-heading">
        <div class="flex items-center justify-between gap-2 border-b border-line bg-subtle px-4 py-2"><h2 id="equipment-heading" class="text-sm font-semibold">Equipment</h2><span class="rounded-md bg-accent-soft px-2 py-0.5 text-xs font-medium text-accent-ink">{{ $items->count() }} allocations</span></div>
        @if($items->isEmpty())<p class="p-4 text-sm text-muted">No equipment allocated.</p>@else
            <div class="overflow-x-auto"><table class="st-table"><thead class="bg-subtle/50"><tr><th scope="col">Product</th><th scope="col">Required + spare</th><th scope="col">Picked / out / returned</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead><tbody>
                @foreach($items as $line)
                    <tr><td class="font-medium">{{ $line['product']['name'] ?? 'Unknown' }}</td><td class="st-num">{{ $line['required_quantity'] }} + {{ $line['spare_quantity'] }}</td><td class="st-num">{{ $line['picked_quantity'] }} / {{ $line['checked_out_quantity'] }} / {{ $line['returned_quantity'] }}</td><td class="whitespace-nowrap text-right">
                        @if($can['items_edit'] && $job['allows_allocation_edits'])<button class="mr-3 font-medium text-accent hover:underline" type="button" data-edit-item data-url="{{ route('jobs.items.update', [$job['id'], $line['id']]) }}" data-required="{{ $line['required_quantity'] }}" data-spare="{{ $line['spare_quantity'] }}" data-name="{{ $line['product']['name'] ?? 'Unknown' }}" aria-label="Edit {{ $line['product']['name'] ?? 'Unknown' }} allocation">Edit</button>@endif
                        @if($can['items_delete'] && $job['allows_allocation_edits'])<form class="inline" method="POST" action="{{ route('jobs.items.destroy', [$job['id'], $line['id']]) }}" onsubmit="return confirm('Remove this allocation?')">@csrf @method('DELETE')<button class="font-medium text-red-700 hover:underline dark:text-red-400" type="submit" aria-label="Remove {{ $line['product']['name'] ?? 'Unknown' }} allocation">Remove</button></form>@endif
                    </td></tr>
                @endforeach
            </tbody></table></div>
        @endif
    </section>
    @if($can['items_edit'] && $job['allows_allocation_edits'])
            <form id="edit-allocation" method="POST" action="#" class="st-card hidden space-y-3 p-4" data-initial-item="{{ old('editing_item_id') }}">
                @csrf @method('PUT')
                <input type="hidden" name="editing_item_id" id="editing-item-id" value="{{ old('editing_item_id') }}">
                <h2 class="font-semibold">Edit allocation: <span id="allocation-product"></span></h2>
                <div class="flex flex-wrap gap-3"><label class="block text-sm">Required <x-input id="allocation-required" name="required_quantity" type="number" min="0" max="100000" value="{{ old('required_quantity') }}" required /></label><label class="block text-sm">Spare <x-input id="allocation-spare" name="spare_quantity" type="number" min="0" max="100000" value="{{ old('spare_quantity') }}" required /></label></div>
                @if($can['override'])<div class="space-y-2"><label class="flex items-center gap-2"><input id="allocation-override" type="checkbox" name="override" value="1" @checked(old('override'))> Override overbooking</label><label id="allocation-override-reason" class="{{ old('override') ? '' : 'hidden' }}">Override reason <x-input name="override_reason" value="{{ old('override_reason') }}" minlength="5" maxlength="500" placeholder="Override reason" /></label></div>@endif
                @error('required_quantity')<p role="alert" class="text-sm text-red-600">{{ $message }}</p>@enderror
                @error('spare_quantity')<p role="alert" class="text-sm text-red-600">{{ $message }}</p>@enderror
                @error('overbooking')<p role="alert" class="text-sm text-red-600">{{ $message }}</p>@enderror
                @error('item')<p role="alert" class="text-sm text-red-600">{{ $message }}</p>@enderror
                @error('override_reason')<p role="alert" class="text-sm text-red-600">{{ $message }}</p>@enderror
                <div class="flex gap-2"><x-button type="submit" color="primary">Save allocation</x-button><x-button id="cancel-allocation" type="button" color="secondary">Cancel</x-button></div>
            </form>
    @endif
    @if($can['items_create'] && $job['allows_allocation_edits'])
        <section class="st-card" aria-labelledby="add-equipment-heading">
            <h2 id="add-equipment-heading" class="rounded-t-lg border-b border-line bg-subtle px-4 py-2 text-sm font-semibold">Add equipment</h2>
            <form method="POST" action="{{ route('jobs.items.store', $job['id']) }}" class="space-y-3 p-4">
                @csrf
                <div class="max-w-xl"><label for="product-lookup" class="block text-sm">Product</label>
                    <div class="relative mt-1" data-lookup data-endpoint="{{ route('jobs.lookup', 'products') }}" data-initial-id="{{ old('product_id') }}" data-initial-label="{{ old('product_label') }}">
                        <input id="product-lookup" type="text" role="combobox" aria-expanded="false" aria-controls="product-options" autocomplete="off" class="block w-full rounded-md border border-line bg-surface px-3 py-2 pr-16 text-ink" placeholder="Choose product" value="{{ old('product_label') }}">
                        <input type="hidden" name="product_id" value="{{ old('product_id') }}"><input type="hidden" name="product_label" value="{{ old('product_label') }}">
                        <button type="button" class="absolute right-2 top-2 text-sm text-muted hover:text-ink" aria-label="Clear product">Clear</button><ul id="product-options" role="listbox" class="absolute z-40 mt-1 hidden max-h-60 w-full overflow-auto rounded-md border border-line bg-surface text-ink shadow-lg"></ul>
                    </div>
                </div>
                @error('product_id')<p role="alert" class="text-sm text-red-600">{{ $message }}</p>@enderror
                <div class="flex flex-wrap gap-3"><label class="block text-sm">Required <x-input name="required_quantity" type="number" min="0" max="100000" value="{{ old('required_quantity', 1) }}" required /></label><label class="block text-sm">Spare <x-input name="spare_quantity" type="number" min="0" max="100000" value="{{ old('spare_quantity', 0) }}" /></label></div>
                <input type="hidden" name="with_accessories" value="0"><label class="flex items-center gap-2"><input type="checkbox" name="with_accessories" value="1" @checked(old('with_accessories', true))> Include accessories</label>
                @if($can['override'])<div class="space-y-2"><label class="flex items-center gap-2"><input id="item-override" type="checkbox" name="override" value="1" @checked(old('override'))> Override overbooking</label><label id="item-override-reason" class="{{ old('override') ? '' : 'hidden' }}">Override reason <x-input name="override_reason" value="{{ old('override_reason') }}" minlength="5" maxlength="500" placeholder="Override reason" /></label></div>@endif
                @error('required_quantity')<p role="alert" class="text-sm text-red-600">{{ $message }}</p>@enderror
                @error('spare_quantity')<p role="alert" class="text-sm text-red-600">{{ $message }}</p>@enderror
                @error('overbooking')<p role="alert" class="text-sm text-red-600">{{ $message }}</p>@enderror
                @error('item')<p role="alert" class="text-sm text-red-600">{{ $message }}</p>@enderror
                @error('override_reason')<p role="alert" class="text-sm text-red-600">{{ $message }}</p>@enderror
                <x-button type="submit" color="primary">Add</x-button>
            </form>
        </section>
    @endif
    @if($findings)<section class="st-card overflow-hidden" aria-labelledby="validation-heading"><h2 id="validation-heading" class="border-b border-line bg-subtle px-4 py-2 text-sm font-semibold">Validation</h2><ul class="space-y-1 p-4 text-sm">@foreach($findings as $finding)<li class="rounded-md bg-amber-50 px-3 py-2 text-amber-900 dark:bg-amber-900/30 dark:text-amber-200">{{ $finding['message'] }}</li>@endforeach</ul></section>@endif
    @if($stageHistory->isNotEmpty())<section class="st-card overflow-hidden" aria-labelledby="stage-history-heading"><h2 id="stage-history-heading" class="border-b border-line bg-subtle px-4 py-2 text-sm font-semibold">Stage history</h2><ul class="divide-y divide-line">@foreach($stageHistory as $history)<li class="px-4 py-2 text-sm">{{ $history['from'] ?: 'Created' }} → {{ $history['to'] }} <span class="text-muted">· {{ $history['user'] ?: 'System' }} · @if($history['at'])<time data-format="datetime" datetime="{{ $history['at'] }}">{{ \Illuminate\Support\Carbon::parse($history['at'])->format('M j, Y g:i A') }}</time>@endif</span> @if($history['notes'])<span class="block whitespace-pre-wrap text-muted">{{ $history['notes'] }}</span>@endif</li>@endforeach</ul></section>@endif
</div>
<script>
(() => {
    document.querySelectorAll('time[data-format="datetime"]').forEach((time) => {
        const date = new Date(time.dateTime);
        if (!Number.isNaN(date.getTime())) time.textContent = new Intl.DateTimeFormat(undefined, {dateStyle: 'medium', timeStyle: 'short'}).format(date);
    });
    const showOverrideReason = (checkbox, field) => {
        if (checkbox && field) {
            const update = () => field.classList.toggle('hidden', !checkbox.checked);
            checkbox.addEventListener('change', update);
            update();
        }
    };
    showOverrideReason(document.getElementById('stage-override'), document.getElementById('stage-override-reason'));
    showOverrideReason(document.getElementById('item-override'), document.getElementById('item-override-reason'));
    showOverrideReason(document.getElementById('allocation-override'), document.getElementById('allocation-override-reason'));
    const allocationOverride = document.getElementById('allocation-override');
    const allocationReason = document.getElementById('allocation-override-reason');
    if (allocationOverride && allocationReason) allocationReason.querySelector('input').required = allocationOverride.checked;
    const editForm = document.getElementById('edit-allocation');
    document.querySelectorAll('[data-edit-item]').forEach((button) => button.addEventListener('click', () => {
        editForm.action = button.dataset.url;
        document.getElementById('allocation-product').textContent = button.dataset.name;
        document.getElementById('allocation-required').value = button.dataset.required;
        document.getElementById('allocation-spare').value = button.dataset.spare;
        document.getElementById('editing-item-id').value = button.dataset.url.split('/').pop();
        editForm.classList.remove('hidden');
        editForm.scrollIntoView({behavior: 'smooth', block: 'center'});
    }));
    const initialItemId = editForm?.dataset.initialItem;
    if (initialItemId) {
        document.querySelector(`[data-edit-item][data-url$="/${CSS.escape(initialItemId)}"]`)?.click();
        const oldRequired = @json(old('required_quantity'));
        const oldSpare = @json(old('spare_quantity'));
        if (oldRequired !== null) document.getElementById('allocation-required').value = oldRequired;
        if (oldSpare !== null) document.getElementById('allocation-spare').value = oldSpare;
    }
    document.getElementById('cancel-allocation')?.addEventListener('click', () => editForm.classList.add('hidden'));
    document.querySelectorAll('[data-lookup]').forEach((lookup) => {
        const input = lookup.querySelector('[role=combobox]');
        const hidden = lookup.querySelectorAll('input[type=hidden]');
        const list = lookup.querySelector('[role=listbox]');
        let timer;
        let active = -1;
        const close = () => { list.classList.add('hidden'); input.setAttribute('aria-expanded', 'false'); };
        const choose = (option) => { hidden[0].value = option.value; hidden[1].value = option.label; input.value = option.label; close(); };
        const search = () => {
            clearTimeout(timer);
            timer = setTimeout(async () => {
                const url = new URL(lookup.dataset.endpoint, window.location.origin);
                url.searchParams.set('q', input.value);
                const response = await fetch(url, {headers: {'Accept': 'application/json'}});
                const options = response.ok ? await response.json() : [];
                list.replaceChildren();
                active = -1;
                options.forEach((option, index) => {
                    const row = document.createElement('li');
                    const button = document.createElement('button');
                    button.type = 'button'; button.className = 'block w-full px-3 py-2 text-left hover:bg-hover focus:bg-hover'; button.setAttribute('role', 'option');
                    button.dataset.value = option.value; button.dataset.label = option.label;
                    button.textContent = option.label + (option.description ? ' · ' + option.description : '');
                    button.addEventListener('focus', () => { active = index; });
                    button.addEventListener('mousedown', (event) => { event.preventDefault(); choose(option); });
                    button.addEventListener('click', () => choose(option));
                    row.append(button); list.append(row);
                });
                if (options.length) { list.classList.remove('hidden'); input.setAttribute('aria-expanded', 'true'); } else close();
            }, 200);
        };
        input.addEventListener('focus', search);
        input.addEventListener('input', search);
        input.addEventListener('keydown', (event) => {
            const options = [...list.querySelectorAll('button')];
            if (event.key === 'ArrowDown' && options.length) { event.preventDefault(); active = (active + 1) % options.length; options[active].focus(); }
            if (event.key === 'Enter' && options.length) { event.preventDefault(); choose({value: options[Math.max(active, 0)].dataset.value, label: options[Math.max(active, 0)].dataset.label}); }
            if (event.key === 'Escape') close();
        });
        list.addEventListener('keydown', (event) => {
            const options = [...list.querySelectorAll('button')];
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                active = (active + (event.key === 'ArrowDown' ? 1 : -1) + options.length) % options.length;
                options[active]?.focus();
            }
            if (event.key === 'Escape') { close(); input.focus(); }
        });
        lookup.querySelector('button[aria-label^=Clear]').addEventListener('click', () => { hidden[0].value = ''; hidden[1].value = ''; input.value = ''; close(); input.focus(); });
        lookup.addEventListener('focusout', (event) => { if (!lookup.contains(event.relatedTarget)) setTimeout(close, 100); });
    });
})();
</script>
@endcomponent
