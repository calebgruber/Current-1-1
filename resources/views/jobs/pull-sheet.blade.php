@component('layouts.app')
<div class="mx-auto max-w-7xl space-y-5">
    <div class="flex flex-wrap items-end justify-between gap-3"><h1 class="text-2xl font-bold">Pull sheet · {{ $job['number'] }} {{ $job['name'] }}</h1><a class="text-primary-700 underline" href="{{ route('jobs.show', $job['id']) }}">Back to job</a></div>
    <p>Picked {{ $sheet['totals']['picked'] }} / {{ $sheet['totals']['pull'] }}</p>
    @forelse($sheet['sections'] as $section)
        <x-card bordered shadowless header="{{ $section['name'] }}">
            <div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr class="border-b">@foreach(['Product','Location','Required','Spare','Pull','Picked'] as $heading)<th class="p-3">{{ $heading }}</th>@endforeach</tr></thead><tbody>
                @foreach($section['lines'] as $line)
                    <tr class="border-b"><td class="p-3">{{ $line['product'] }} {{ $line['sku'] }}</td><td class="p-3">{{ $line['location'] ?: '—' }}</td><td class="p-3">{{ $line['required'] }}</td><td class="p-3">{{ $line['spare'] }}</td><td class="p-3">{{ $line['pull'] }}</td><td class="p-3">{{ $line['picked'] }}</td></tr>
                    @foreach($line['children'] as $child)<tr class="border-b"><td class="p-3 pl-7">↳ {{ $child['product'] }} {{ $child['sku'] }}</td><td class="p-3">{{ $child['location'] ?: '—' }}</td><td class="p-3">{{ $child['required'] }}</td><td class="p-3">{{ $child['spare'] }}</td><td class="p-3">{{ $child['pull'] }}</td><td class="p-3">{{ $child['picked'] }}</td></tr>@endforeach
                @endforeach
            </tbody></table></div>
        </x-card>
    @empty<p class="text-gray-500">No equipment allocated.</p>@endforelse
</div>
@endcomponent
