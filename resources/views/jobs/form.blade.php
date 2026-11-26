@php($editing = $job !== null)
@component('layouts.app')
<div class="mx-auto max-w-4xl space-y-5">
    <div class="flex flex-wrap items-end justify-between gap-3"><h1 class="text-2xl font-bold">{{ $editing ? 'Edit job' : 'New job' }}</h1><a class="text-primary-700 underline" href="{{ $editing ? route('jobs.show', $job['id']) : route('jobs.index') }}">Cancel</a></div>
    <x-card bordered shadowless>
        <form id="job-form" method="POST" action="{{ $editing ? route('jobs.update', $job['id']) : route('jobs.store') }}" class="space-y-4">
            @csrf @if($editing)@method('PUT')@endif
            @foreach($contacts ?? [] as $index => $contact)
                <input type="hidden" name="contacts[{{ $index }}][contact_id]" value="{{ $contact['contact_id'] }}">
                <input type="hidden" name="contacts[{{ $index }}][role]" value="{{ $contact['role'] ?? '' }}">
            @endforeach
            @if($errors->any())<div class="text-sm text-red-600" role="alert">{{ $errors->first() }}</div>@endif
            <label class="block text-sm">Name <x-input name="name" value="{{ old('name', $job['name'] ?? '') }}" maxlength="255" required /></label>
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach([
                    ['name' => 'client_contact_id', 'label' => 'Client', 'type' => 'contacts', 'id' => $job['client_contact_id'] ?? null, 'label_value' => $job['client_label'] ?? null],
                    ['name' => 'venue_location_id', 'label' => 'Venue', 'type' => 'locations', 'id' => $job['venue_location_id'] ?? null, 'label_value' => $job['venue_label'] ?? null],
                    ['name' => 'owner_id', 'label' => 'Owner', 'type' => 'users', 'id' => $job['owner_id'] ?? null, 'label_value' => $job['owner_label'] ?? null],
                ] as $lookup)
                    <label class="block text-sm">{{ $lookup['label'] }}
                        <div class="relative mt-1" data-lookup data-endpoint="{{ route('jobs.lookup', $lookup['type']) }}" data-initial-id="{{ old($lookup['name'], $lookup['id']) }}" data-initial-label="{{ old(str_replace('_id', '_label', $lookup['name']), $lookup['label_value']) }}">
                            <input type="text" role="combobox" aria-expanded="false" autocomplete="off" class="block w-full rounded border-gray-300" placeholder="Search {{ strtolower($lookup['label']) }}" value="{{ old(str_replace('_id', '_label', $lookup['name']), $lookup['label_value']) }}">
                            <input type="hidden" name="{{ $lookup['name'] }}" value="{{ old($lookup['name'], $lookup['id']) }}">
                            <input type="hidden" name="{{ str_replace('_id', '_label', $lookup['name']) }}" value="{{ old(str_replace('_id', '_label', $lookup['name']), $lookup['label_value']) }}">
                            <button type="button" class="absolute right-2 top-1 text-sm text-gray-600" aria-label="Clear {{ strtolower($lookup['label']) }}">Clear</button>
                            <ul role="listbox" class="absolute z-40 mt-1 hidden max-h-60 w-full overflow-auto rounded border bg-white shadow"></ul>
                        </div>
                        @error($lookup['name'])<span class="text-sm text-red-600">{{ $message }}</span>@enderror
                    </label>
                @endforeach
                <label class="block text-sm">Workflow status <x-input name="workflow_status" value="{{ old('workflow_status', $job['workflow_status'] ?? '') }}" maxlength="64" /></label>
                @foreach(['prep_starts_at' => 'Prep start', 'starts_at' => 'Event start', 'ends_at' => 'Event end', 'returns_at' => 'Return'] as $field => $label)
                    <label class="block text-sm">{{ $label }} <x-input class="job-datetime" name="{{ $field }}" type="datetime-local" value="{{ old($field, $job[$field] ?? '') }}" data-original="{{ old($field, $job[$field] ?? '') }}" /></label>
                @endforeach
            </div>
            <label class="block text-sm">Description <textarea name="description" rows="4" class="mt-1 block w-full rounded border-gray-300">{{ old('description', $job['description'] ?? '') }}</textarea></label>
            <section class="space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-2"><h2 class="font-semibold">Custom fields</h2>@if(auth()->user()->can('settings.custom_fields.view') && \Illuminate\Support\Facades\Route::has('settings.custom-fields.index'))<a class="text-primary-700 underline" href="{{ route('settings.custom-fields.index') }}">Manage fields</a>@endif</div>
                @if($customFieldSchema)<p class="text-sm text-gray-500">Additional job information.</p>@else<p class="text-sm text-gray-500">No custom fields configured for jobs.</p>@endif
                @include('partials.custom-fields', ['customFields' => $customFields, 'customFieldSchema' => $customFieldSchema])
            </section>
            @if($confirmedHold && $editing)
                <div class="space-y-2"><label class="flex items-center gap-2"><input id="job-override" type="checkbox" name="override" value="1" @checked(old('override'))> Override overbooking (requires permission)</label><label id="job-override-reason" class="block text-sm {{ old('override') ? '' : 'hidden' }}">Reason <x-input name="override_reason" value="{{ old('override_reason') }}" minlength="5" maxlength="500" /></label></div>
            @endif
            <x-button type="submit" color="primary">Save job</x-button>
        </form>
    </x-card>
</div>
<script>
(() => {
    const form = document.getElementById('job-form');
    const localise = (input) => {
        const date = new Date(input.dataset.original || input.value);
        if (!Number.isNaN(date.getTime())) input.value = new Date(date.getTime() - date.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
        input.dataset.localValue = input.value;
    };
    form.querySelectorAll('.job-datetime').forEach(localise);
    form.addEventListener('submit', () => {
        form.querySelectorAll('.job-datetime').forEach((input) => {
            if (input.value && input.value === input.dataset.localValue && input.dataset.original) input.value = input.dataset.original;
            else if (input.value) input.value = new Date(input.value).toISOString();
        });
    });
    const override = document.getElementById('job-override');
    const reason = document.getElementById('job-override-reason');
    if (override && reason) {
        const update = () => { reason.classList.toggle('hidden', !override.checked); reason.querySelector('input').required = override.checked; };
        override.addEventListener('change', update); update();
    }
    document.querySelectorAll('[data-lookup]').forEach((lookup) => {
        const input = lookup.querySelector('[role=combobox]');
        const hidden = lookup.querySelectorAll('input[type=hidden]');
        const id = hidden[0];
        const label = hidden[1];
        const list = lookup.querySelector('[role=listbox]');
        let timer;
        let active = -1;
        const close = () => { list.classList.add('hidden'); input.setAttribute('aria-expanded', 'false'); };
        const choose = (option) => {
            id.value = option.value;
            label.value = option.label;
            input.value = option.label;
            list.replaceChildren();
            close();
        };
        const lookupOptions = async () => {
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
                    button.type = 'button';
                    button.className = 'block w-full px-3 py-2 text-left hover:bg-gray-100';
                    button.setAttribute('role', 'option');
                    button.dataset.value = option.value;
                    button.dataset.label = option.label;
                    button.textContent = option.label + (option.description ? ' · ' + option.description : '');
                    button.addEventListener('mousedown', (event) => { event.preventDefault(); choose(option); });
                    button.addEventListener('click', () => choose(option));
                    row.append(button);
                    list.append(row);
                    if (index === 0) active = 0;
                });
                if (options.length) {
                    list.classList.remove('hidden');
                    input.setAttribute('aria-expanded', 'true');
                } else close();
            }, 200);
        };
        input.addEventListener('focus', lookupOptions);
        input.addEventListener('input', lookupOptions);
        input.addEventListener('keydown', (event) => {
            const options = [...list.querySelectorAll('button')];
            if (event.key === 'ArrowDown' && options.length) { event.preventDefault(); active = (active + 1) % options.length; options[active].focus(); }
            if (event.key === 'Escape') close();
            if (event.key === 'Enter' && options.length) { event.preventDefault(); choose({value: options[Math.max(active, 0)].dataset.value, label: options[Math.max(active, 0)].dataset.label}); }
        });
        lookup.querySelector('button[aria-label^=Clear]').addEventListener('click', () => { id.value = ''; label.value = ''; input.value = ''; close(); input.focus(); });
        lookup.addEventListener('focusout', (event) => { if (!lookup.contains(event.relatedTarget)) setTimeout(close, 100); });
    });
})();
</script>
@endcomponent
