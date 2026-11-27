@component('layouts.app')
<div class="mx-auto max-w-7xl space-y-4">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <a class="text-xs font-medium text-accent hover:underline" href="{{ route('jobs.show', $job['id']) }}">Back to job</a>
            <h1 class="mt-1 text-xl font-bold tracking-tight text-ink">Pull sheet</h1>
            <p class="mt-1 text-sm text-muted">{{ $job['number'] }} · {{ $job['name'] }}</p>
        </div>
        <div class="rounded-lg border border-line bg-surface px-4 py-2">
            <p class="text-xs font-medium uppercase tracking-wide text-muted">Picked / pull</p>
            <p class="mt-1 text-lg font-semibold tabular-nums text-ink">{{ $sheet['totals']['picked'] }} <span class="text-muted">/</span> {{ $sheet['totals']['pull'] }}</p>
        </div>
    </div>

    @forelse($sheet['sections'] as $section)
        <section class="st-card overflow-hidden">
            <h2 class="border-b border-line bg-subtle px-4 py-2 text-sm font-semibold">{{ $section['name'] }}</h2>
            <div class="overflow-x-auto">
                <table class="st-table">
                    <thead class="bg-subtle/50"><tr>@foreach(['Product','Location','Required','Spare','Pull','Picked'] as $heading)<th scope="col">{{ $heading }}</th>@endforeach</tr></thead>
                    <tbody>
                        @foreach($section['lines'] as $line)
                            <tr>
                                <td><span class="font-medium">{{ $line['product'] }}</span> <span class="text-muted">{{ $line['sku'] }}</span></td>
                                <td>{{ $line['location'] ?: '—' }}</td><td class="st-num">{{ $line['required'] }}</td><td class="st-num">{{ $line['spare'] }}</td><td class="st-num font-semibold">{{ $line['pull'] }}</td><td class="st-num">{{ $line['picked'] }}</td>
                            </tr>
                            @foreach($line['children'] as $child)
                                <tr>
                                    <td><span class="inline-block pl-4"><span aria-hidden="true" class="text-muted">↳</span> <span class="sr-only">Accessory for {{ $line['product'] }}:</span> {{ $child['product'] }} <span class="text-muted">{{ $child['sku'] }}</span></span></td>
                                    <td>{{ $child['location'] ?: '—' }}</td><td class="st-num">{{ $child['required'] }}</td><td class="st-num">{{ $child['spare'] }}</td><td class="st-num font-semibold">{{ $child['pull'] }}</td><td class="st-num">{{ $child['picked'] }}</td>
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @empty
        <div class="st-card p-4 text-sm text-muted">No equipment allocated.</div>
    @endforelse
</div>
@endcomponent
