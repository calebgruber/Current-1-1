@component('layouts.app')
<div class="mx-auto max-w-7xl space-y-5">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div><h1 class="text-2xl font-bold">{{ $job['number'] }} · {{ $job['name'] }}</h1><p class="mt-1"><span class="rounded border px-2 py-1 text-xs">{{ $job['stage_label'] }}</span></p></div>
        <div class="flex flex-wrap gap-3"><a class="text-primary-700 underline" href="{{ route('jobs.index') }}">All jobs</a>@if($can['edit'])<a class="text-primary-700 underline" href="{{ route('jobs.edit', $job['id']) }}">Edit</a>@endif @if($can['delete'])<form method="POST" action="{{ route('jobs.destroy', $job['id']) }}" onsubmit="return confirm('Delete this job?')">@csrf @method('DELETE')<button class="text-primary-700 underline" type="submit">Delete</button></form>@endif</div>
    </div>
    @if(session('success'))<x-alert color="green">{{ session('success') }}</x-alert>@endif
    @if(session('error'))<x-alert color="red">{{ session('error') }}</x-alert>@endif
    <div class="grid gap-4 lg:grid-cols-2">
        <x-card bordered shadowless header="Job details">
            <p>@if($job['starts_at'])<time data-format="datetime" datetime="{{ $job['starts_at'] }}">{{ \Illuminate\Support\Carbon::parse($job['starts_at'])->format('M j, Y g:i A') }}</time>@else—@endif → @if($job['returns_at'])<time data-format="datetime" datetime="{{ $job['returns_at'] }}">{{ \Illuminate\Support\Carbon::parse($job['returns_at'])->format('M j, Y g:i A') }}</time>@else—@endif</p>
            @if($job['description'])<p class="mt-2 whitespace-pre-wrap">{{ $job['description'] }}</p>@endif
            <a class="mt-3 inline-block text-primary-700 underline" href="{{ route('jobs.pull-sheet', $job['id']) }}">Pull sheet</a>
            @if($contacts->isNotEmpty())<h3 class="mt-4 font-semibold">Contacts</h3><ul class="mt-1 space-y-1">@foreach($contacts as $contact)<li>{{ $contact['name'] }}{{ $contact['role'] ? ' · '.$contact['role'] : '' }}{{ $contact['email'] ? ' · '.$contact['email'] : '' }}</li>@endforeach</ul>@endif
        </x-card>
        @if($customFieldSchema || (auth()->user()->can('settings.custom_fields.view') && \Illuminate\Support\Facades\Route::has('settings.custom-fields.index')))
            <x-card bordered shadowless header="Custom fields">
                @if(auth()->user()->can('settings.custom_fields.view') && \Illuminate\Support\Facades\Route::has('settings.custom-fields.index'))<a class="text-primary-700 underline" href="{{ route('settings.custom-fields.index') }}">Manage fields</a>@endif
                @include('partials.custom-field-display', ['customFieldSchema' => $customFieldSchema, 'customFields' => $customFields])
                @unless($customFieldSchema)<p class="text-sm text-gray-500">No custom fields configured for jobs.</p>@endunless
            </x-card>
        @endif
    </div>
    @if(collect($job['allowed_transitions'])->contains(fn ($transition) => $transition['allowed']))
        <x-card bordered shadowless header="Move stage">
            <form method="POST" action="{{ route('jobs.transition', $job['id']) }}" class="flex flex-wrap items-end gap-3">
                @csrf
                <label class="block text-sm">New stage <select name="stage" required class="mt-1 block w-full rounded border-gray-300"><option value="">Choose stage</option>@foreach($job['allowed_transitions'] as $transition)@if($transition['allowed'])<option value="{{ $transition['value'] }}" @selected(old('stage') === $transition['value'])>{{ $transition['label'] }}</option>@endif @endforeach</select></label>
                <label class="block text-sm">Notes <x-input name="notes" value="{{ old('notes') }}" maxlength="1000" placeholder="Notes (optional)" /></label>
                @if($can['override'])<div class="space-y-2"><label class="flex items-center gap-2"><input id="stage-override" type="checkbox" name="override" value="1" @checked(old('override'))> Override overbooking</label><label id="stage-override-reason" class="{{ old('override') ? '' : 'hidden' }}"><x-input name="override_reason" value="{{ old('override_reason') }}" minlength="5" maxlength="500" placeholder="Override reason" /></label></div>@endif
                <x-button type="submit" color="primary">Move</x-button>
                @error('stage')<span role="alert" class="text-sm text-red-600">{{ $message }}</span>@enderror
                @error('override_reason')<span role="alert" class="text-sm text-red-600">{{ $message }}</span>@enderror
            </form>
        </x-card>
    @endif
    <x-card bordered shadowless header="Equipment">
        @if($items->isEmpty())<p class="text-sm text-gray-500">No equipment allocated.</p>@else
            <div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr class="border-b"><th class="p-3">Product</th><th class="p-3">Required + spare</th><th class="p-3">Picked / out / returned</th><th class="p-3"></th></tr></thead><tbody>
                @foreach($items as $line)
                    <tr class="border-b"><td class="p-3">{{ $line['product']['name'] ?? 'Unknown' }}</td><td class="p-3">{{ $line['required_quantity'] }} + {{ $line['spare_quantity'] }}</td><td class="p-3">{{ $line['picked_quantity'] }} / {{ $line['checked_out_quantity'] }} / {{ $line['returned_quantity'] }}</td><td class="p-3 whitespace-nowrap">
                        @if($can['items_edit'] && $job['allows_allocation_edits'])<button class="mr-3 text-primary-700 underline" type="button" data-edit-item data-url="{{ route('jobs.items.update', [$job['id'], $line['id']]) }}" data-required="{{ $line['required_quantity'] }}" data-spare="{{ $line['spare_quantity'] }}" data-name="{{ $line['product']['name'] ?? 'Unknown' }}">Edit</button>@endif
                        @if($can['items_delete'] && $job['allows_allocation_edits'])<form class="inline" method="POST" action="{{ route('jobs.items.destroy', [$job['id'], $line['id']]) }}" onsubmit="return confirm('Remove this allocation?')">@csrf @method('DELETE')<button class="text-primary-700 underline" type="submit">Remove</button></form>@endif
                    </td></tr>
                @endforeach
            </tbody></table></div>
        @endif
    </x-card>
    @if($can['items_edit'] && $job['allows_allocation_edits'])
        <x-card bordered shadowless>
            <form id="edit-allocation" method="POST" action="#" class="hidden space-y-3" data-initial-item="{{ old('editing_item_id') }}">
                @csrf @method('PUT')
                <input type="hidden" name="editing_item_id" id="editing-item-id" value="{{ old('editing_item_id') }}">
                <h2 class="font-semibold">Edit allocation: <span id="allocation-product"></span></h2>
                <div class="flex flex-wrap gap-3"><label class="block text-sm">Required <x-input id="allocation-required" name="required_quantity" type="number" min="0" max="100000" value="{{ old('required_quantity') }}" required /></label><label class="block text-sm">Spare <x-input id="allocation-spare" name="spare_quantity" type="number" min="0" max="100000" value="{{ old('spare_quantity') }}" required /></label></div>
                @if($can['override'])<div class="space-y-2"><label class="flex items-center gap-2"><input id="allocation-override" type="checkbox" name="override" value="1" @checked(old('override'))> Override overbooking</label><label id="allocation-override-reason" class="{{ old('override') ? '' : 'hidden' }}"><x-input name="override_reason" value="{{ old('override_reason') }}" minlength="5" maxlength="500" placeholder="Override reason" /></label></div>@endif
                @error('required_quantity')<p role="alert" class="text-sm text-red-600">{{ $message }}</p>@enderror
                @error('spare_quantity')<p role="alert" class="text-sm text-red-600">{{ $message }}</p>@enderror
                @error('overbooking')<p role="alert" class="text-sm text-red-600">{{ $message }}</p>@enderror
                @error('item')<p role="alert" class="text-sm text-red-600">{{ $message }}</p>@enderror
                @error('override_reason')<p role="alert" class="text-sm text-red-600">{{ $message }}</p>@enderror
                <div class="flex gap-2"><x-button type="submit" color="primary">Save allocation</x-button><x-button id="cancel-allocation" type="button" color="secondary">Cancel</x-button></div>
            </form>
        </x-card>
    @endif
    @if($can['items_create'] && $job['allows_allocation_edits'])
        <x-card bordered shadowless header="Add equipment">
            <form method="POST" action="{{ route('jobs.items.store', $job['id']) }}" class="space-y-3">
                @csrf
                <label class="block text-sm">Product
                    <div class="relative mt-1" data-lookup data-endpoint="{{ route('jobs.lookup', 'products') }}" data-initial-id="{{ old('product_id') }}" data-initial-label="{{ old('product_label') }}">
                        <input type="text" role="combobox" aria-expanded="false" autocomplete="off" class="block w-full rounded border-gray-300" placeholder="Choose product" value="{{ old('product_label') }}">
                        <input type="hidden" name="product_id" value="{{ old('product_id') }}"><input type="hidden" name="product_label" value="{{ old('product_label') }}">
                        <button type="button" class="absolute right-2 top-1 text-sm text-gray-600" aria-label="Clear product">Clear</button><ul role="listbox" class="absolute z-40 mt-1 hidden max-h-60 w-full overflow-auto rounded border bg-white shadow"></ul>
                    </div>
                </label>
                @error('product_id')<p role="alert" class="text-sm text-red-600">{{ $message }}</p>@enderror
                <div class="flex flex-wrap gap-3"><label class="block text-sm">Required <x-input name="required_quantity" type="number" min="0" max="100000" value="{{ old('required_quantity', 1) }}" required /></label><label class="block text-sm">Spare <x-input name="spare_quantity" type="number" min="0" max="100000" value="{{ old('spare_quantity', 0) }}" /></label></div>
                <input type="hidden" name="with_accessories" value="0"><label class="flex items-center gap-2"><input type="checkbox" name="with_accessories" value="1" @checked(old('with_accessories', true))> Include accessories</label>
                @if($can['override'])<div class="space-y-2"><label class="flex items-center gap-2"><input id="item-override" type="checkbox" name="override" value="1" @checked(old('override'))> Override overbooking</label><label id="item-override-reason" class="{{ old('override') ? '' : 'hidden' }}"><x-input name="override_reason" value="{{ old('override_reason') }}" minlength="5" maxlength="500" placeholder="Override reason" /></label></div>@endif
                @error('required_quantity')<p role="alert" class="text-sm text-red-600">{{ $message }}</p>@enderror
                @error('spare_quantity')<p role="alert" class="text-sm text-red-600">{{ $message }}</p>@enderror
                @error('overbooking')<p role="alert" class="text-sm text-red-600">{{ $message }}</p>@enderror
                @error('item')<p role="alert" class="text-sm text-red-600">{{ $message }}</p>@enderror
                @error('override_reason')<p role="alert" class="text-sm text-red-600">{{ $message }}</p>@enderror
                <x-button type="submit" color="primary">Add</x-button>
            </form>
        </x-card>
    @endif
    @if($findings)<x-card bordered shadowless header="Validation"><ul class="space-y-1">@foreach($findings as $finding)<li>{{ $finding['message'] }}</li>@endforeach</ul></x-card>@endif
    @if($stageHistory->isNotEmpty())<x-card bordered shadowless header="Stage history"><ul class="space-y-2">@foreach($stageHistory as $history)<li>{{ $history['from'] ?: 'Created' }} → {{ $history['to'] }} · {{ $history['user'] ?: 'System' }} · @if($history['at'])<time data-format="datetime" datetime="{{ $history['at'] }}">{{ \Illuminate\Support\Carbon::parse($history['at'])->format('M j, Y g:i A') }}</time>@endif @if($history['notes'])<span class="block whitespace-pre-wrap text-sm text-gray-600">{{ $history['notes'] }}</span>@endif</li>@endforeach</ul></x-card>@endif
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
        let active = 0;
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
                active = 0;
                options.forEach((option, index) => {
                    const row = document.createElement('li');
                    const button = document.createElement('button');
                    button.type = 'button'; button.className = 'block w-full px-3 py-2 text-left hover:bg-gray-100'; button.setAttribute('role', 'option');
                    button.dataset.value = option.value; button.dataset.label = option.label;
                    button.textContent = option.label + (option.description ? ' · ' + option.description : '');
                    button.addEventListener('mousedown', (event) => { event.preventDefault(); choose(option); });
                    button.addEventListener('click', () => choose(option));
                    row.append(button); list.append(row);
                    if (index === 0) active = 0;
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
        lookup.querySelector('button[aria-label^=Clear]').addEventListener('click', () => { hidden[0].value = ''; hidden[1].value = ''; input.value = ''; close(); input.focus(); });
        lookup.addEventListener('focusout', (event) => { if (!lookup.contains(event.relatedTarget)) setTimeout(close, 100); });
    });
})();
</script>
@endcomponent
