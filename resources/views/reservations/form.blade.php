@php($editing = $reservation !== null)
@component('layouts.app')
<div class="mx-auto max-w-4xl space-y-5">
    <div class="flex flex-wrap items-end justify-between gap-3"><h1 class="text-2xl font-bold">{{ $editing ? 'Edit reservation' : 'New reservation' }}</h1><a class="text-primary-700 underline" href="{{ $editing ? route('reservations.show', $reservation['id']) : route('reservations.index') }}">Cancel</a></div>
    <x-card bordered shadowless>
        <form id="reservation-form" method="POST" action="{{ $editing ? route('reservations.update', $reservation['id']) : route('reservations.store') }}" class="space-y-4">
            @csrf @if($editing)@method('PUT')@endif
            @if($errors->any())<div class="text-sm text-red-600" role="alert">{{ $errors->first() }}</div>@endif
            @foreach([
                ['name' => 'product_id', 'label' => 'Product', 'type' => 'products', 'id' => $reservation['product_id'] ?? $defaults['product_id'] ?? null, 'label_value' => $reservation['product'] ?? $defaults['product_label'] ?? null],
                ['name' => 'job_id', 'label' => 'Job (optional)', 'type' => 'jobs', 'id' => $reservation['job_id'] ?? $defaults['job_id'] ?? null, 'label_value' => (($reservation['job'] ?? null) ? $reservation['job']['number'].' — '.$reservation['job']['name'] : null) ?? $defaults['job_label'] ?? null],
            ] as $lookup)
                <label class="block text-sm">{{ $lookup['label'] }}
                    <div class="relative mt-1" data-lookup data-endpoint="{{ route('jobs.lookup', $lookup['type']) }}">
                        <input type="text" role="combobox" aria-expanded="false" autocomplete="off" class="block w-full rounded border-gray-300" placeholder="Search {{ strtolower($lookup['label']) }}" value="{{ old($lookup['name'].'_label', $lookup['label_value']) }}">
                        <input type="hidden" name="{{ $lookup['name'] }}" value="{{ old($lookup['name'], $lookup['id']) }}"><input type="hidden" name="{{ $lookup['name'].'_label' }}" value="{{ old($lookup['name'].'_label', $lookup['label_value']) }}">
                        <button type="button" class="absolute right-2 top-1 text-sm text-gray-600" aria-label="Clear {{ strtolower($lookup['label']) }}">Clear</button><ul role="listbox" class="absolute z-40 mt-1 hidden max-h-60 w-full overflow-auto rounded border bg-white shadow"></ul>
                    </div>
                    @error($lookup['name'])<span class="text-sm text-red-600">{{ $message }}</span>@enderror
                </label>
            @endforeach
            <label class="block text-sm">Name <x-input name="name" value="{{ old('name', $reservation['name'] ?? '') }}" maxlength="255" placeholder="Required without a job" /></label>
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="block text-sm">Quantity <x-input name="quantity" type="number" min="1" max="100000" value="{{ old('quantity', $reservation['quantity'] ?? 1) }}" required /></label>
                <label class="block text-sm">Spare <x-input name="spare_quantity" type="number" min="0" max="100000" value="{{ old('spare_quantity', $reservation['spare_quantity'] ?? 0) }}" /></label>
                <label class="block text-sm">Start <x-input class="reservation-datetime" name="starts_at" type="datetime-local" value="{{ old('starts_at', $reservation['starts_at'] ?? $defaults['starts_at'] ?? '') }}" data-original="{{ old('starts_at', $reservation['starts_at'] ?? $defaults['starts_at'] ?? '') }}" required /></label>
                <label class="block text-sm">End <x-input class="reservation-datetime" name="ends_at" type="datetime-local" value="{{ old('ends_at', $reservation['ends_at'] ?? $defaults['ends_at'] ?? '') }}" data-original="{{ old('ends_at', $reservation['ends_at'] ?? $defaults['ends_at'] ?? '') }}" required /></label>
            </div>
            <label class="block text-sm">Status <select name="status" class="mt-1 block w-full rounded border-gray-300">@foreach($statuses as $option)<option value="{{ $option['value'] }}" @selected(old('status', $reservation['status'] ?? 'tentative') === $option['value'])>{{ $option['label'] }}</option>@endforeach</select></label>
            <label class="block text-sm">Notes <textarea name="notes" rows="3" class="mt-1 block w-full rounded border-gray-300">{{ old('notes', $reservation['notes'] ?? '') }}</textarea></label>
            @include('partials.custom-fields', ['customFields' => $customFields, 'customFieldSchema' => $customFieldSchema])
            @if($canOverride)<div class="space-y-2"><label class="flex items-center gap-2"><input id="reservation-override" type="checkbox" name="override" value="1" @checked(old('override'))> Override overbooking</label><label id="reservation-override-reason" class="{{ old('override') ? '' : 'hidden' }}">Reason <x-input name="override_reason" value="{{ old('override_reason') }}" minlength="5" maxlength="500" /></label></div>@endif
            <x-button type="submit" color="primary">Save reservation</x-button>
        </form>
    </x-card>
</div>
<script>
(() => {
    const form = document.getElementById('reservation-form');
    const localise = (input) => {
        const date = new Date(input.dataset.original || input.value);
        if (!Number.isNaN(date.getTime())) input.value = new Date(date.getTime() - date.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
        input.dataset.localValue = input.value;
    };
    form.querySelectorAll('.reservation-datetime').forEach(localise);
    form.addEventListener('submit', () => form.querySelectorAll('.reservation-datetime').forEach((input) => {
        if (input.value && input.value === input.dataset.localValue && input.dataset.original) input.value = input.dataset.original;
        else if (input.value) input.value = new Date(input.value).toISOString();
    }));
    const override = document.getElementById('reservation-override');
    const reason = document.getElementById('reservation-override-reason');
    if (override && reason) {
        const update = () => { reason.classList.toggle('hidden', !override.checked); reason.querySelector('input').required = override.checked; };
        override.addEventListener('change', update); update();
    }
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
                list.replaceChildren(); active = 0;
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
        input.addEventListener('focus', search); input.addEventListener('input', search);
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
