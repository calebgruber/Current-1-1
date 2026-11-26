@component('layouts.app')
<div class="mx-auto max-w-7xl space-y-5">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div><h1 class="text-2xl font-bold">Jobs</h1><p class="text-sm text-gray-500">Manage productions and equipment allocations.</p></div>
        @can('jobs.jobs.create')<x-button :href="route('jobs.create')" color="primary">New job</x-button>@endcan
    </div>
    @if(session('success'))<x-alert color="green">{{ session('success') }}</x-alert>@endif
    @if(session('error'))<x-alert color="red">{{ session('error') }}</x-alert>@endif
    <form id="job-filters" method="GET" action="{{ route('jobs.index') }}" class="grid gap-3 rounded border border-gray-200 p-4 sm:grid-cols-2 lg:grid-cols-4">
        <input type="hidden" name="view" value="{{ $view }}">
        <label class="block text-sm">Search jobs <x-input name="search" type="search" value="{{ $filters['search'] }}" placeholder="Number, name, or client" /></label>
        <label class="block text-sm">Stage <select name="stage" class="mt-1 block w-full rounded border-gray-300"><option value="">All stages</option>@foreach($stages as $option)<option value="{{ $option['value'] }}" @selected($filters['stage'] === $option['value'])>{{ $option['label'] }} ({{ $stageCounts[$option['value']] ?? 0 }})</option>@endforeach</select></label>
        <label class="block text-sm">From <x-input name="from" type="date" value="{{ $filters['from'] }}" /></label>
        <label class="block text-sm">To <x-input name="to" type="date" value="{{ $filters['to'] }}" /></label>
        <label class="block text-sm">Sort by <select name="sort" class="mt-1 block w-full rounded border-gray-300">@foreach(['starts_at' => 'Event start', 'number' => 'Job number', 'name' => 'Name', 'stage' => 'Stage', 'created_at' => 'Created'] as $value => $label)<option value="{{ $value }}" @selected($sort === $value)>{{ $label }}</option>@endforeach</select></label>
        <label class="block text-sm">Direction <select name="direction" class="mt-1 block w-full rounded border-gray-300"><option value="asc" @selected($direction === 'asc')>Ascending</option><option value="desc" @selected($direction === 'desc')>Descending</option></select></label>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="archived" value="1" @checked($filters['archived'])> Include archived jobs</label>
        <div class="flex flex-wrap items-end gap-2"><x-button type="submit" color="primary">Filter</x-button><x-button type="button" color="secondary" onclick="const input = document.querySelector('#job-filters [name=view]'); input.value = input.value === 'list' ? 'board' : 'list'; document.getElementById('job-filters').requestSubmit();">{{ $view === 'list' ? 'Board view' : 'List view' }}</x-button><x-button :href="route('jobs.index')" color="secondary">Reset</x-button></div>
    </form>
    @if($jobs)
        <x-card bordered shadowless>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm"><thead><tr class="border-b"><th class="p-3">Job</th><th class="p-3">Stage</th><th class="p-3">Client</th><th class="p-3">Start</th></tr></thead><tbody>
                    @forelse($jobs as $job)
                    <tr class="border-b"><td class="p-3"><a class="text-primary-700 underline" href="{{ route('jobs.show', $job['id']) }}">{{ $job['number'] }} · {{ $job['name'] }}</a></td><td class="p-3"><span class="rounded border px-2 py-1 text-xs">{{ $job['stage_label'] }}</span></td><td class="p-3">{{ $job['client'] ?: '—' }}</td><td class="p-3">@if($job['starts_at'])<time data-format="date" datetime="{{ $job['starts_at'] }}">{{ \Illuminate\Support\Carbon::parse($job['starts_at'])->format('M j, Y') }}</time>@else—@endif</td></tr>
                    @empty<tr><td colspan="4" class="p-4 text-gray-500">No jobs found.</td></tr>@endforelse
                </tbody></table>
            </div>
            <div class="mt-4">{{ $jobs->links() }}</div>
        </x-card>
    @elseif($board)
        <div class="grid gap-4 md:grid-cols-3">
            @foreach($stages as $stage)
                @if(array_key_exists($stage['value'], $board))
                    <x-card bordered shadowless header="{{ $stage['label'] }}">
                        @forelse($board[$stage['value']] as $job)
                            <p class="border-b py-2"><a class="text-primary-700 underline" href="{{ route('jobs.show', $job['id']) }}">{{ $job['number'] }} · {{ $job['name'] }}</a></p>
                        @empty<p class="text-sm text-gray-500">No jobs</p>@endforelse
                    </x-card>
                @endif
            @endforeach
        </div>
    @endif
</div>
<script>
document.querySelectorAll('time[data-format="date"]').forEach((time) => {
    const date = new Date(time.dateTime);
    if (!Number.isNaN(date.getTime())) time.textContent = new Intl.DateTimeFormat(undefined, {dateStyle: 'medium'}).format(date);
});
</script>
@endcomponent
