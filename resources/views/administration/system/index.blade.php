@component('layouts.app')
<div class="mx-auto max-w-5xl space-y-5">
    <div><h1 class="text-2xl font-bold">System</h1><p class="text-sm text-gray-500">Health and migration status, pending database migrations, and framework cache maintenance.</p></div>
    @if(session('success'))<x-alert color="green">{{ session('success') }}</x-alert>@endif
    @if(session('error'))<x-alert color="red">{{ session('error') }}</x-alert>@endif
    <x-card bordered shadowless><h2 class="mb-3 text-lg font-semibold">Health</h2><p>Database connection: {{ $status['database'] === 'ok' ? 'Available' : 'Unavailable' }}</p><p>Storage directory: {{ $status['storage_writable'] ? 'Writable' : 'Not writable' }}</p><p class="mt-2 text-sm text-gray-500">These checks do not test queues, backups, scheduler, or individual storage disks.</p></x-card>
    <x-card bordered shadowless>
        <h2 class="mb-3 text-lg font-semibold">Migrations</h2>
        @if($status['migrations']['status'] === 'available')
            <p>{{ $status['migrations']['applied'] }} recorded · {{ count($status['migrations']['pending'] ?? []) }} pending</p>
            @if($status['migrations']['pending'])<ul class="list-inside list-disc text-sm">@foreach($status['migrations']['pending'] as $name)<li>{{ $name }}</li>@endforeach</ul>@endif
        @else<p>{{ $status['migrations']['status'] === 'repository_missing' ? 'Migration repository is missing.' : 'Migration status is unavailable.' }}</p>@endif
        <p class="mt-2 text-sm text-gray-500">Status covers the default database and registered migration paths.</p>
        @can('admin.system.admin')
            <p class="mt-3 font-semibold">Take and verify a database backup before changing the schema.</p>
            <p class="mt-2 text-sm text-gray-500">Runs only pending migrations from the installed application on the default database. This does not install application code or reset the database. Migrations may change or remove data. Recent password confirmation is required; return here and submit again if prompted.</p>
            <form method="POST" action="{{ route('admin.system.migrations.run') }}" class="mt-3 space-y-2">
                @csrf
                <label for="migration-confirmation" class="block text-sm font-medium">Type MIGRATE to confirm you have a verified backup and want to run pending migrations.</label>
                <input id="migration-confirmation" name="confirmation" type="text" required autocomplete="off" pattern="MIGRATE" class="rounded border border-gray-300 px-3 py-2">
                @error('confirmation')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                <div><x-button type="submit" color="primary">Run pending migrations</x-button></div>
            </form>
        @endcan
    </x-card>
    @can('admin.system.admin')
        <x-card bordered shadowless>
            <h2 class="mb-2 text-lg font-semibold">Framework caches</h2>
            <p class="mb-3 text-sm text-gray-500">Clears generated configuration, route, or compiled view files. Configuration and route caches must be rebuilt by your deployment process if needed. The application cache store is not flushed.</p>
            <div class="flex flex-wrap gap-2">@foreach(['config', 'route', 'view'] as $cache)<form method="POST" action="{{ route('admin.system.cache.clear') }}" onsubmit="return confirm('Clear the {{ $cache }} cache?')">@csrf<input type="hidden" name="cache" value="{{ $cache }}"><x-button type="submit" color="secondary">Clear {{ $cache }} cache</x-button></form>@endforeach</div>
        </x-card>
    @endcan
</div>
@endcomponent
