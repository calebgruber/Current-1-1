@component('layouts.app')
<div class="mx-auto max-w-7xl space-y-5">
    <div class="flex flex-wrap items-end justify-between gap-3"><div><h1 class="text-2xl font-bold">Contacts</h1><p class="text-sm text-gray-500">People and organizations.</p></div>@can('contacts.contacts.create')<x-button :href="route('contacts.create')" color="primary">Add contact</x-button>@endcan</div>
    @if(session('success'))<x-alert color="green">{{ session('success') }}</x-alert>@endif
    @if(session('error'))<x-alert color="red">{{ session('error') }}</x-alert>@endif
    <form method="GET" action="{{ route('contacts.index') }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
        <label class="block text-sm">Search <x-input name="search" type="search" value="{{ $filters['search'] }}" placeholder="Name, email, phone, title" /></label>
        <label class="block text-sm">Type <select name="type" class="mt-1 block w-full rounded border-gray-300"><option value="">All types</option>@foreach(['person' => 'Person', 'organization' => 'Organization'] as $value => $label)<option value="{{ $value }}" @selected($filters['type'] === $value)>{{ $label }}</option>@endforeach</select></label>
        <label class="block text-sm">Sort by <select name="sort" class="mt-1 block w-full rounded border-gray-300">@foreach(['name' => 'Name', 'email' => 'Email', 'type' => 'Type', 'created_at' => 'Created'] as $value => $label)<option value="{{ $value }}" @selected($filters['sort'] === $value)>{{ $label }}</option>@endforeach</select></label>
        <label class="block text-sm">Direction <select name="direction" class="mt-1 block w-full rounded border-gray-300"><option value="asc" @selected($filters['direction'] === 'asc')>Ascending</option><option value="desc" @selected($filters['direction'] === 'desc')>Descending</option></select></label>
        <div class="flex items-end gap-2"><x-button type="submit" color="primary">Filter contacts</x-button><x-button :href="route('contacts.index')" color="secondary">Reset</x-button></div>
    </form>
    @can('contacts.contacts.export')<p><a class="text-primary-700 underline" href="{{ route('contacts.export', request()->query()) }}">Export CSV</a></p>@endcan
    <x-card bordered shadowless><div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr class="border-b">@foreach(['Name','Type','Organization','Email','Phone','Title','Jobs','People'] as $heading)<th class="p-3">{{ $heading }}</th>@endforeach</tr></thead><tbody>
        @forelse($contacts as $contact)<tr class="border-b"><td class="p-3"><a class="text-primary-700 underline" href="{{ route('contacts.show', $contact['id']) }}">{{ $contact['name'] }}</a></td><td class="p-3">{{ ucfirst($contact['type']) }}</td><td class="p-3">{{ $contact['organization']['name'] ?? '—' }}</td><td class="p-3">{{ $contact['email'] ?? '—' }}</td><td class="p-3">{{ $contact['phone'] ?? '—' }}</td><td class="p-3">{{ $contact['title'] ?? '—' }}</td><td class="p-3">{{ $contact['jobs_count'] }}</td><td class="p-3">{{ $contact['members_count'] }}</td></tr>
        @empty<tr><td colspan="8" class="p-4 text-gray-500">No contacts found.</td></tr>@endforelse
    </tbody></table></div><nav class="mt-4 flex justify-between" aria-label="Pagination"><span>Page {{ $contacts->currentPage() }} of {{ $contacts->lastPage() }}</span><div class="flex gap-2">@if($contacts->previousPageUrl())<x-button :href="$contacts->previousPageUrl()" color="secondary">Previous</x-button>@endif @if($contacts->nextPageUrl())<x-button :href="$contacts->nextPageUrl()" color="secondary">Next</x-button>@endif</div></nav></x-card>
</div>
@endcomponent
