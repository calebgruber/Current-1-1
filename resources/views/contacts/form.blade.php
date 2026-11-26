@php($editing = $contact !== null)
@component('layouts.app')
<div class="mx-auto max-w-4xl space-y-5">
    <div class="flex flex-wrap items-end justify-between gap-3"><h1 class="text-2xl font-bold">{{ $editing ? 'Edit contact' : 'New contact' }}</h1><a class="text-primary-700 underline" href="{{ $editing ? route('contacts.show', $contact['id']) : route('contacts.index') }}">Cancel</a></div>
    <x-card bordered shadowless>
        <form id="contact-form" method="POST" action="{{ $editing ? route('contacts.update', $contact['id']) : route('contacts.store') }}" class="space-y-4">
            @csrf @if($editing)@method('PUT')@endif
            @if($errors->any())<div class="text-sm text-red-600" role="alert">{{ $errors->first() }}</div>@endif
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="block text-sm">Type <select name="type" class="mt-1 block w-full rounded border-gray-300" required><option value="person" @selected(old('type', $defaults['type']) === 'person')>Person</option><option value="organization" @selected(old('type', $defaults['type']) === 'organization')>Organization</option></select>@error('type')<span class="text-red-600">{{ $message }}</span>@enderror</label>
                <label class="block text-sm">Name <x-input name="name" value="{{ old('name', $contact['name'] ?? '') }}" maxlength="255" required />@error('name')<span class="text-red-600">{{ $message }}</span>@enderror</label>
                <label id="organization-field" class="block text-sm">Organization
                    <div class="relative mt-1" data-lookup data-endpoint="{{ route('contacts.lookup', ['type' => 'organization']) }}" data-initial-id="{{ old('organization_id', $contact['organization_id'] ?? $defaults['organization']['value'] ?? '') }}" data-initial-label="{{ $defaults['organization']['label'] ?? '' }}">
                        <input type="text" role="combobox" aria-expanded="false" aria-autocomplete="list" autocomplete="off" class="block w-full rounded border-gray-300" placeholder="Find an organization" value="{{ old('organization_label', $defaults['organization']['label'] ?? '') }}">
                        <input type="hidden" name="organization_id" value="{{ old('organization_id', $contact['organization_id'] ?? $defaults['organization']['value'] ?? '') }}">
                        <input type="hidden" name="organization_label" value="{{ old('organization_label', $defaults['organization']['label'] ?? '') }}">
                        <button type="button" class="absolute right-2 top-1 text-sm text-gray-600" aria-label="Clear organization">Clear</button>
                        <ul role="listbox" class="absolute z-40 mt-1 hidden max-h-60 w-full overflow-auto rounded border bg-white shadow"></ul>
                    </div>
                    @error('organization_id')<span class="text-red-600">{{ $message }}</span>@enderror
                </label>
                <label class="block text-sm">Title <x-input name="title" value="{{ old('title', $contact['title'] ?? '') }}" maxlength="255" /></label>
                <label class="block text-sm">Email <x-input name="email" type="email" value="{{ old('email', $contact['email'] ?? '') }}" maxlength="255" />@error('email')<span class="text-red-600">{{ $message }}</span>@enderror</label>
                <label class="block text-sm">Phone <x-input name="phone" type="tel" value="{{ old('phone', $contact['phone'] ?? '') }}" maxlength="64" />@error('phone')<span class="text-red-600">{{ $message }}</span>@enderror</label>
            </div>
            <label class="block text-sm">Address <textarea name="address" rows="2" maxlength="2000" class="mt-1 block w-full rounded border-gray-300">{{ old('address', $contact['address'] ?? '') }}</textarea></label>
            <label class="block text-sm">Notes <textarea name="notes" rows="3" maxlength="20000" class="mt-1 block w-full rounded border-gray-300">{{ old('notes', $contact['notes'] ?? '') }}</textarea></label>
            @include('partials.custom-fields', ['customFields' => $customFields, 'customFieldSchema' => $customFieldSchema])
            <x-button type="submit" color="primary">Save contact</x-button>
        </form>
    </x-card>
</div>
<script>
(() => {
    const form = document.getElementById('contact-form');
    const type = form.querySelector('[name=type]');
    const organization = document.getElementById('organization-field');
    const lookup = organization.querySelector('[data-lookup]');
    const input = lookup.querySelector('[role=combobox]');
    const id = lookup.querySelector('[name=organization_id]');
    const label = lookup.querySelector('[name=organization_label]');
    const list = lookup.querySelector('[role=listbox]');
    let timer;
    const close = () => { list.classList.add('hidden'); input.setAttribute('aria-expanded', 'false'); };
    const choose = (option) => { id.value = option.value; label.value = option.label; input.value = option.label; close(); };
    const showOrganization = () => { organization.classList.toggle('hidden', type.value !== 'person'); };
    const search = () => {
        clearTimeout(timer);
        timer = setTimeout(async () => {
            const url = new URL(lookup.dataset.endpoint, window.location.origin);
            url.searchParams.set('q', input.value);
            const response = await fetch(url, {headers: {'Accept': 'application/json'}});
            const options = response.ok ? await response.json() : [];
            list.replaceChildren();
            options.forEach((option) => {
                const row = document.createElement('li');
                const button = document.createElement('button');
                button.type = 'button'; button.className = 'block w-full px-3 py-2 text-left hover:bg-gray-100'; button.setAttribute('role', 'option');
                button.textContent = option.label + (option.description ? ' · ' + option.description : '');
                button.addEventListener('mousedown', (event) => event.preventDefault());
                button.addEventListener('click', () => choose(option)); row.append(button); list.append(row);
            });
            if (options.length) { list.classList.remove('hidden'); input.setAttribute('aria-expanded', 'true'); } else close();
        }, 200);
    };
    type.addEventListener('change', showOrganization); showOrganization();
    input.addEventListener('focus', search); input.addEventListener('input', () => { id.value = ''; label.value = ''; search(); });
    input.addEventListener('keydown', (event) => { if (event.key === 'Escape') close(); if (event.key === 'ArrowDown') list.querySelector('button')?.focus(); });
    lookup.querySelector('[aria-label="Clear organization"]').addEventListener('click', () => { id.value = ''; label.value = ''; input.value = ''; close(); input.focus(); });
    lookup.addEventListener('focusout', (event) => { if (!lookup.contains(event.relatedTarget)) setTimeout(close, 100); });
})();
</script>
@endcomponent
