@component('layouts.app')
<div class="mx-auto max-w-5xl space-y-5">
    <div class="flex flex-wrap items-end justify-between gap-3"><div><h1 class="text-2xl font-bold">{{ $contact['name'] }}</h1><p class="text-sm text-gray-500">{{ ucfirst($contact['type']) }}</p></div><div class="flex gap-2"><a class="text-primary-700 underline" href="{{ route('contacts.index') }}">Contacts</a>@can('contacts.contacts.edit')<x-button :href="route('contacts.edit', $contact['id'])" color="secondary">Edit</x-button>@endcan</div></div>
    @if(session('success'))<x-alert color="green">{{ session('success') }}</x-alert>@endif
    @if(session('error'))<x-alert color="red">{{ session('error') }}</x-alert>@endif
    <x-card bordered shadowless>
        <h2 class="mb-3 text-lg font-semibold">Contact details</h2>
        @if($contact['organization'])<p>Organization: <a class="text-primary-700 underline" href="{{ route('contacts.show', $contact['organization']['id']) }}">{{ $contact['organization']['name'] }}</a></p>@endif
        <p>Email: {{ $contact['email'] ?: '—' }} · Phone: {{ $contact['phone'] ?: '—' }}</p>
        @if($contact['title'])<p>Title: {{ $contact['title'] }}</p>@endif
        @if($contact['address'])<p class="whitespace-pre-line">Address: {{ $contact['address'] }}</p>@endif
        @if($contact['notes'])<p class="whitespace-pre-line">Notes: {{ $contact['notes'] }}</p>@endif
        @include('partials.custom-field-display', ['customFieldSchema' => $customFieldSchema, 'customFields' => $customFields])
    </x-card>
    @if($contact['type'] === 'organization')<x-card bordered shadowless><h2 class="mb-3 text-lg font-semibold">People</h2><ul class="space-y-1">@forelse($members as $member)<li><a class="text-primary-700 underline" href="{{ route('contacts.show', $member['id']) }}">{{ $member['name'] }}</a> · {{ $member['email'] ?: '—' }}</li>@empty<li class="text-gray-500">No linked people.</li>@endforelse</ul></x-card>@endif
    <x-card bordered shadowless><h2 class="mb-3 text-lg font-semibold">Jobs</h2><ul class="space-y-2">@forelse($jobs as $job)<li>@can('jobs.jobs.view')<a class="text-primary-700 underline" href="{{ route('jobs.show', $job['id']) }}">{{ $job['number'] }} — {{ $job['name'] }}</a>@else{{ $job['number'] }} — {{ $job['name'] }}@endcan ({{ $job['role'] }})</li>@empty<li class="text-gray-500">No linked jobs.</li>@endforelse</ul></x-card>
</div>
@endcomponent
