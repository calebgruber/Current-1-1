<div class="mx-auto max-w-7xl space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Welcome, {{ Str::before(auth()->user()->name, ' ') }}</h1>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Here’s what’s happening across your productions.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if(Route::has('availability.index'))
                <x-button color="secondary" :href="route('availability.index')">Availability</x-button>
            @endif
            @if(Route::has('warehouse.index'))
                <x-button color="secondary" :href="route('warehouse.index')">Warehouse</x-button>
            @endif
            @can('jobs.jobs.create')
                <x-button color="primary" :href="route('jobs.create')">New job</x-button>
            @endcan
        </div>
    </div>

    @if($stageCounts)
        <section aria-label="Job pipeline" class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-7">
            @foreach(\App\Domain\Jobs\Enums\JobStage::cases() as $stage)
                @unless(in_array($stage, [\App\Domain\Jobs\Enums\JobStage::Closed, \App\Domain\Jobs\Enums\JobStage::Archived], true))
                    <a href="{{ route('jobs.index', ['stage' => $stage->value]) }}" class="block rounded-none border border-gray-200 bg-white p-3 transition hover:border-primary-400 hover:bg-primary-50 dark:border-dark-600 dark:bg-dark-700 dark:hover:bg-dark-600">
                        <span class="text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">{{ $stage->label() }}</span>
                        <span class="mt-2 block text-2xl font-bold text-primary-700 dark:text-primary-300">{{ $stageCounts[$stage->value] ?? 0 }}</span>
                    </a>
                @endunless
            @endforeach
        </section>
    @endif

    <div class="grid gap-6 xl:grid-cols-3">
        @if($upcoming)
            <x-card shadowless bordered header="Upcoming in the next 3 weeks" class="xl:col-span-2">
                @forelse($upcoming as $job)
                    <a href="{{ $job['url'] ?? '#' }}" class="flex flex-wrap items-center gap-3 border-b border-gray-100 py-3 last:border-0 dark:border-dark-600">
                        <span class="w-20 shrink-0 font-mono text-xs text-gray-500">{{ $job['number'] }}</span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-medium text-gray-900 dark:text-white">{{ $job['name'] }}</span>
                            <span class="block truncate text-xs text-gray-500">{{ $job['client'] ?? 'No client' }} · {{ $job['starts_at']?->format('M j') }} → {{ $job['ends_at']?->format('M j') }}</span>
                        </span>
                        <x-badge color="primary">{{ $job['stage_label'] }}</x-badge>
                    </a>
                @empty
                    <p class="py-6 text-center text-sm text-gray-500">Nothing scheduled in the next 21 days.</p>
                @endforelse
                @if(Route::has('jobs.index'))
                    <x-slot:footer>
                        <x-button color="secondary" :href="route('jobs.index')">All jobs</x-button>
                    </x-slot:footer>
                @endif
            </x-card>
        @endif

        <div class="space-y-6">
            @if($overdue?->isNotEmpty())
                <x-card shadowless bordered header="Overdue returns">
                    @foreach($overdue as $job)
                        <a href="{{ $job['url'] ?? '#' }}" class="flex items-center justify-between gap-3 border-b border-gray-100 py-2 last:border-0 dark:border-dark-600">
                            <span class="truncate text-sm font-medium">{{ $job['number'] }} · {{ $job['name'] }}</span>
                            <span class="shrink-0 text-xs font-semibold text-red-700 dark:text-red-300">Overdue</span>
                        </a>
                    @endforeach
                </x-card>
            @endif

            @if($assetCounts || $maintenanceOpen !== null)
                <div class="grid grid-cols-2 gap-3">
                    @if($maintenanceOpen !== null)
                        <x-card shadowless bordered>
                            <span class="text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Open maintenance</span>
                            <span class="mt-2 block text-2xl font-bold {{ $maintenanceOpen ? 'text-amber-700 dark:text-amber-300' : 'text-emerald-700 dark:text-emerald-300' }}">{{ $maintenanceOpen }}</span>
                        </x-card>
                    @endif
                    @foreach($assetCounts ?? [] as $status => $count)
                        <x-card shadowless bordered>
                            <span class="text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">{{ Str::headline($status) }}</span>
                            <span class="mt-2 block text-2xl font-bold text-primary-700 dark:text-primary-300">{{ $count }}</span>
                        </x-card>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
        @if($warehouseQueue)
            <x-card shadowless bordered header="Warehouse queue">
                @forelse($warehouseQueue as $job)
                    <a href="{{ $job['url'] ?? '#' }}" class="flex items-center gap-3 border-b border-gray-100 py-3 last:border-0 dark:border-dark-600">
                        <x-badge color="primary">{{ $job['stage_label'] }}</x-badge>
                        <span class="min-w-0 flex-1 truncate text-sm font-medium">{{ $job['number'] }} · {{ $job['name'] }}</span>
                        <span class="text-xs text-gray-500">{{ ($job['prep_starts_at'] ?? $job['starts_at'])?->format('M j') }}</span>
                    </a>
                @empty
                    <p class="py-5 text-center text-sm text-gray-500">The warehouse queue is clear.</p>
                @endforelse
            </x-card>
        @endif

        @if($recentScans)
            <x-card shadowless bordered header="Recent scans">
                @forelse($recentScans as $scan)
                    <div class="flex items-center gap-3 border-b border-gray-100 py-3 last:border-0 dark:border-dark-600">
                        <x-badge :color="$scan['result'] === 'success' ? 'green' : ($scan['result'] === 'warning' ? 'yellow' : 'red')">{{ Str::headline($scan['mode']) }}</x-badge>
                        <span class="font-mono text-xs">{{ $scan['code'] }}</span>
                        <span class="min-w-0 flex-1 truncate text-xs text-gray-600 dark:text-gray-300">{{ $scan['message'] }}</span>
                    </div>
                @empty
                    <p class="py-5 text-center text-sm text-gray-500">No scans yet.</p>
                @endforelse
            </x-card>
        @endif
    </div>
</div>
