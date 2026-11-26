@component('layouts.app')
<div class="mx-auto max-w-7xl space-y-5">
    <div class="flex flex-wrap items-end justify-between gap-4"><div><h1 class="text-2xl font-bold">Warehouse</h1><p class="text-sm text-gray-500">Jobs moving through warehouse operations.</p></div>@if($canDisplay)<x-button :href="route('warehouse.display')" color="secondary">Fullscreen display</x-button>@endif</div>
    <nav class="flex flex-wrap gap-3" aria-label="Warehouse stations">@foreach($stations->where('allowed') as $station)<x-button :href="route('warehouse.'.$station['slug'])" color="secondary">{{ $station['label'] }}</x-button>@endforeach</nav>
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach($queues as $queue)
            <section class="rounded-lg border border-gray-200 p-4 dark:border-gray-700"><h2 class="font-semibold">{{ $queue['label'] }}</h2>
                @if(!$queue['jobs'])<p class="mt-2 text-sm text-gray-500">No jobs</p>@else<ul class="mt-2 divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($queue['jobs'] as $job)<li class="py-3"><p class="font-medium">{{ $job['number'] }} · {{ $job['name'] }}</p><p class="text-sm text-gray-500">{{ $job['starts_at'] ? \Illuminate\Support\Carbon::parse($job['starts_at'])->format('M j, Y') : 'No start date' }} · picked {{ $job['picked'] }}/{{ $job['pull'] }} · out {{ $job['checked_out'] }} · returned {{ $job['returned'] }}</p>
                        <div class="mt-2 flex flex-wrap gap-2">@foreach($stations->where('allowed') as $station)<a class="text-sm text-primary-700 underline" href="{{ route('warehouse.'.$station['slug'], $job['id']) }}">{{ $station['label'] }}</a>@endforeach @if($canDisplay)<a class="text-sm text-primary-700 underline" href="{{ route('warehouse.display', $job['id']) }}">Display</a>@endif</div>
                    </li>@endforeach
                </ul>@endif
            </section>
        @endforeach
    </div>
</div>
@endcomponent
