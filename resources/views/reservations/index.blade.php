@component('layouts.app')
<div class="mx-auto max-w-7xl space-y-5">
    <div class="flex flex-wrap items-end justify-between gap-4"><h1 class="text-2xl font-bold">Reservations</h1>@can('reservations.reservations.create')<x-button :href="route('reservations.create')" color="primary">New reservation</x-button>@endcan</div>
    @if(session('success'))<x-alert color="green">{{ session('success') }}</x-alert>@endif
    @if(session('error'))<x-alert color="red">{{ session('error') }}</x-alert>@endif
    <form id="reservation-filters" method="GET" action="{{ route('reservations.index') }}" class="grid gap-3 rounded border border-gray-200 p-4 sm:grid-cols-2 lg:grid-cols-4">
        <input type="hidden" name="view" value="{{ $view }}">
        <label class="block text-sm">Search <x-input name="search" type="search" value="{{ $filters['search'] }}" placeholder="Reservation, product, or job" /></label>
        <label class="block text-sm">Status <select name="status" class="mt-1 block w-full rounded border-gray-300"><option value="">All statuses</option>@foreach($statuses as $option)<option value="{{ $option['value'] }}" @selected($filters['status'] === $option['value'])>{{ $option['label'] }}</option>@endforeach</select></label>
        <label class="block text-sm">Source <select name="source" class="mt-1 block w-full rounded border-gray-300"><option value="">All sources</option><option value="standalone" @selected($filters['source'] === 'standalone')>Standalone</option><option value="job" @selected($filters['source'] === 'job')>Job allocation</option></select></label>
        <label class="block text-sm">Product ID <x-input name="product_id" type="number" min="1" value="{{ $filters['product_id'] ?? '' }}" /></label>
        <label class="block text-sm">Job ID <x-input name="job_id" type="number" min="1" value="{{ $filters['job_id'] ?? '' }}" /></label>
        <label class="block text-sm">From <x-input name="from" type="date" value="{{ $filters['from'] }}" /></label>
        <label class="block text-sm">To <x-input name="to" type="date" value="{{ $filters['to'] }}" /></label>
        <div class="flex flex-wrap items-end gap-2"><x-button type="submit" color="primary">Filter</x-button><x-button type="button" color="secondary" onclick="const input = document.querySelector('#reservation-filters [name=view]'); input.value = input.value === 'list' ? 'timeline' : 'list'; document.getElementById('reservation-filters').requestSubmit();">{{ $view === 'list' ? 'Timeline' : 'List' }}</x-button><x-button :href="route('reservations.index')" color="secondary">Reset</x-button></div>
    </form>
    @if($reservations)
        <x-card bordered shadowless>
            <div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr class="border-b">@foreach(['Reservation','Product','Quantity','Status','Period'] as $heading)<th class="p-3">{{ $heading }}</th>@endforeach</tr></thead><tbody>
                @forelse($reservations as $reservation)
                    <tr class="border-b"><td class="p-3"><a class="text-primary-700 underline" href="{{ route('reservations.show', $reservation['id']) }}">{{ $reservation['label'] }}</a></td><td class="p-3">{{ $reservation['product'] ?: '—' }}</td><td class="p-3">{{ $reservation['total'] }}</td><td class="p-3"><span class="rounded border px-2 py-1 text-xs">{{ $reservation['status'] }}</span></td><td class="p-3">@if($reservation['starts_at'])<time data-format="datetime" datetime="{{ $reservation['starts_at'] }}">{{ \Illuminate\Support\Carbon::parse($reservation['starts_at'])->format('M j, Y g:i A') }}</time>@endif – @if($reservation['ends_at'])<time data-format="datetime" datetime="{{ $reservation['ends_at'] }}">{{ \Illuminate\Support\Carbon::parse($reservation['ends_at'])->format('M j, Y g:i A') }}</time>@endif</td></tr>
                @empty<tr><td colspan="5" class="p-4 text-gray-500">No reservations found.</td></tr>@endforelse
            </tbody></table></div>
            <div class="mt-4">{{ $reservations->links() }}</div>
        </x-card>
    @elseif($timeline)
        <div class="space-y-3">
            @forelse($timeline['groups'] as $group)
                <x-card bordered shadowless header="{{ $group['product'] ?: 'Unknown product' }}">
                    <ul class="space-y-2">@foreach($group['rows'] as $reservation)<li class="border-b py-2"><a class="text-primary-700 underline" href="{{ route('reservations.show', $reservation['id']) }}">{{ $reservation['label'] }}</a> · {{ $reservation['total'] }} · <time data-format="datetime" datetime="{{ $reservation['starts_at'] }}">{{ \Illuminate\Support\Carbon::parse($reservation['starts_at'])->format('M j, Y g:i A') }}</time> – <time data-format="datetime" datetime="{{ $reservation['ends_at'] }}">{{ \Illuminate\Support\Carbon::parse($reservation['ends_at'])->format('M j, Y g:i A') }}</time></li>@endforeach</ul>
                </x-card>
            @empty<p class="text-gray-500">No reservations in this period.</p>@endforelse
        </div>
    @endif
</div>
<script>
document.querySelectorAll('time[data-format="datetime"]').forEach((time) => {
    const date = new Date(time.dateTime);
    if (!Number.isNaN(date.getTime())) time.textContent = new Intl.DateTimeFormat(undefined, {dateStyle: 'medium', timeStyle: 'short'}).format(date);
});
</script>
@endcomponent
