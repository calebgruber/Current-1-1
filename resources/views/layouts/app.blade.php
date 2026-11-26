<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" x-data="tallstackui_darkTheme()">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="light dark">
    <title>{{ config('app.name', 'StageTrack Enterprise') }}</title>
    <tallstackui:script />
    @livewireStyles
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-white font-sans antialiased dark:bg-dark-800" x-cloak>
    @php($settings = app(\App\Domain\Settings\Services\SettingsRepository::class))
    <x-layout>
        <x-slot:top>
            <x-dialog />
            <x-toast />
        </x-slot:top>
        <x-slot:header>
            <x-layout.header>
                <x-slot:right>
                    <span class="hidden font-semibold text-primary-600 dark:text-primary-400 sm:inline">{{ auth()->user()->name }}</span>
                    <x-button color="secondary" :href="route('account.profile')"><x-icon name="user-circle" class="size-4" /> Profile</x-button>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <x-button type="submit" color="red"><x-icon name="arrow-right-start-on-rectangle" class="size-4" /> Sign out</x-button>
                    </form>
                </x-slot:right>
            </x-layout.header>
            <div class="border-b border-gray-200 bg-gradient-to-r from-indigo-50 via-sky-50 to-emerald-50 px-4 py-2 dark:border-dark-600 dark:from-indigo-950 dark:via-sky-950 dark:to-emerald-950">
                <div class="mx-auto flex max-w-screen-2xl items-center gap-2 font-bold text-slate-800 dark:text-white">
                    <x-icon name="building-library" class="size-5 text-indigo-600 dark:text-indigo-300" />
                    <span>{{ $settings->get('general.organization_name', config('app.name', 'StageTrack')) }}</span>
                </div>
            </div>
            <nav aria-label="Main modules" class="border-b border-gray-200 bg-white px-3 dark:border-dark-600 dark:bg-dark-800">
                <div class="mx-auto flex max-w-screen-2xl gap-2 overflow-x-auto py-2">
                    <a role="tab" aria-current="{{ request()->routeIs('dashboard') ? 'page' : 'false' }}" href="{{ route('dashboard') }}" class="module-tab {{ request()->routeIs('dashboard') ? 'module-tab-active bg-indigo-600 text-white' : 'text-indigo-800 hover:bg-indigo-50 dark:text-indigo-200 dark:hover:bg-dark-700' }}"><x-icon name="home" class="size-4" /> Dashboard</a>
                    @can('inventory.products.view')
                        <a role="tab" aria-current="{{ request()->routeIs('inventory.*', 'assets.*', 'maintenance.*', 'availability.*') ? 'page' : 'false' }}" href="{{ route('inventory.products.index') }}" class="module-tab {{ request()->routeIs('inventory.*', 'assets.*', 'maintenance.*', 'availability.*') ? 'module-tab-active bg-sky-600 text-white' : 'text-sky-800 hover:bg-sky-50 dark:text-sky-200 dark:hover:bg-dark-700' }}"><x-icon name="archive-box" class="size-4" /> {{ $settings->get('ui.label_inventory', 'Inventory') }}</a>
                    @endcan
                    @can('inventory.categories.view')
                        <a role="tab" aria-current="{{ request()->routeIs('inventory.categories.*') ? 'page' : 'false' }}" href="{{ route('inventory.categories.index') }}" class="module-tab text-blue-800 hover:bg-blue-50 dark:text-blue-200 dark:hover:bg-dark-700"><x-icon name="tag" class="size-4" /> Categories</a>
                    @endcan
                    @can('inventory.locations.view')
                        <a role="tab" aria-current="{{ request()->routeIs('inventory.locations.*') ? 'page' : 'false' }}" href="{{ route('inventory.locations.index') }}" class="module-tab text-cyan-800 hover:bg-cyan-50 dark:text-cyan-200 dark:hover:bg-dark-700"><x-icon name="map-pin" class="size-4" /> Locations</a>
                    @endcan
                    @can('assets.assets.view')
                        <a role="tab" aria-current="{{ request()->routeIs('assets.*') ? 'page' : 'false' }}" href="{{ route('assets.index') }}" class="module-tab {{ request()->routeIs('assets.*') ? 'module-tab-active bg-blue-600 text-white' : 'text-blue-800 hover:bg-blue-50 dark:text-blue-200 dark:hover:bg-dark-700' }}"><x-icon name="cube" class="size-4" /> Assets</a>
                    @endcan
                    @can('maintenance.records.view')
                        <a role="tab" aria-current="{{ request()->routeIs('maintenance.*') ? 'page' : 'false' }}" href="{{ route('maintenance.index') }}" class="module-tab {{ request()->routeIs('maintenance.*') ? 'module-tab-active bg-rose-600 text-white' : 'text-rose-800 hover:bg-rose-50 dark:text-rose-200 dark:hover:bg-dark-700' }}"><x-icon name="wrench-screwdriver" class="size-4" /> {{ $settings->get('ui.label_maintenance', 'Maintenance') }}</a>
                    @endcan
                    @can('inventory.availability.view')
                        <a role="tab" aria-current="{{ request()->routeIs('availability.*') ? 'page' : 'false' }}" href="{{ route('availability.index') }}" class="module-tab {{ request()->routeIs('availability.*') ? 'module-tab-active bg-teal-700 text-white' : 'text-teal-800 hover:bg-teal-50 dark:text-teal-200 dark:hover:bg-dark-700' }}"><x-icon name="calendar-days" class="size-4" /> Availability</a>
                    @endcan
                    @can('jobs.jobs.view')
                        <a role="tab" aria-current="{{ request()->routeIs('jobs.*') ? 'page' : 'false' }}" href="{{ route('jobs.index') }}" class="module-tab {{ request()->routeIs('jobs.*') ? 'module-tab-active bg-violet-600 text-white' : 'text-violet-800 hover:bg-violet-50 dark:text-violet-200 dark:hover:bg-dark-700' }}"><x-icon name="briefcase" class="size-4" /> {{ $settings->get('ui.label_jobs', 'Jobs') }}</a>
                    @endcan
                    @canany(['warehouse.picking.view', 'warehouse.checkout.view'])
                        <a role="tab" aria-current="{{ request()->routeIs('warehouse.*') ? 'page' : 'false' }}" href="{{ route('warehouse.index') }}" class="module-tab {{ request()->routeIs('warehouse.*') ? 'module-tab-active bg-amber-600 text-white' : 'text-amber-800 hover:bg-amber-50 dark:text-amber-200 dark:hover:bg-dark-700' }}"><x-icon name="qr-code" class="size-4" /> {{ $settings->get('ui.label_warehouse', 'Warehouse') }}</a>
                    @endcanany
                    @can('reservations.reservations.view')
                        <a role="tab" aria-current="{{ request()->routeIs('reservations.*', 'availability.*') ? 'page' : 'false' }}" href="{{ route('reservations.index') }}" class="module-tab {{ request()->routeIs('reservations.*', 'availability.*') ? 'module-tab-active bg-teal-600 text-white' : 'text-teal-800 hover:bg-teal-50 dark:text-teal-200 dark:hover:bg-dark-700' }}"><x-icon name="calendar-days" class="size-4" /> {{ $settings->get('ui.label_reservations', 'Reservations') }}</a>
                    @endcan
                    @can('contacts.contacts.view')
                        <a role="tab" aria-current="{{ request()->routeIs('contacts.*') ? 'page' : 'false' }}" href="{{ route('contacts.index') }}" class="module-tab {{ request()->routeIs('contacts.*') ? 'module-tab-active bg-pink-600 text-white' : 'text-pink-800 hover:bg-pink-50 dark:text-pink-200 dark:hover:bg-dark-700' }}"><x-icon name="users" class="size-4" /> {{ $settings->get('ui.label_contacts', 'Contacts') }}</a>
                    @endcan
                    @can('documents.documents.view')
                        <a role="tab" aria-current="{{ request()->routeIs('documents.*') ? 'page' : 'false' }}" href="{{ route('documents.index') }}" class="module-tab {{ request()->routeIs('documents.*') ? 'module-tab-active bg-cyan-600 text-white' : 'text-cyan-800 hover:bg-cyan-50 dark:text-cyan-200 dark:hover:bg-dark-700' }}"><x-icon name="document-text" class="size-4" /> {{ $settings->get('ui.label_documents', 'Documents') }}</a>
                    @endcan
                    @can('reporting.reports.view')
                        <a role="tab" aria-current="{{ request()->routeIs('reports.*') ? 'page' : 'false' }}" href="{{ route('reports.index') }}" class="module-tab {{ request()->routeIs('reports.*') ? 'module-tab-active bg-emerald-600 text-white' : 'text-emerald-800 hover:bg-emerald-50 dark:text-emerald-200 dark:hover:bg-dark-700' }}"><x-icon name="chart-bar" class="size-4" /> {{ $settings->get('ui.label_reports', 'Reports') }}</a>
                    @endcan
                    @can('rules.rules.view')
                        <a role="tab" aria-current="{{ request()->routeIs('rules.*') ? 'page' : 'false' }}" href="{{ route('rules.index') }}" class="module-tab {{ request()->routeIs('rules.*') ? 'module-tab-active bg-orange-600 text-white' : 'text-orange-800 hover:bg-orange-50 dark:text-orange-200 dark:hover:bg-dark-700' }}"><x-icon name="adjustments-horizontal" class="size-4" /> Rules</a>
                    @endcan
                    <a role="tab" aria-current="{{ request()->routeIs('search.*') ? 'page' : 'false' }}" href="{{ route('search.index') }}" class="module-tab {{ request()->routeIs('search.*') ? 'module-tab-active bg-fuchsia-600 text-white' : 'text-fuchsia-800 hover:bg-fuchsia-50 dark:text-fuchsia-200 dark:hover:bg-dark-700' }}"><x-icon name="magnifying-glass" class="size-4" /> Search</a>
                    @can('settings.general.view')
                        <a role="tab" aria-current="{{ request()->routeIs('settings.general.*') ? 'page' : 'false' }}" href="{{ route('settings.general.index') }}" class="module-tab {{ request()->routeIs('settings.general.*', 'settings.custom-fields.*') ? 'module-tab-active bg-slate-700 text-white' : 'text-slate-800 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-dark-700' }}"><x-icon name="cog-6-tooth" class="size-4" /> Settings</a>
                    @endcan
                    <a role="tab" aria-current="{{ request()->routeIs('account.*') ? 'page' : 'false' }}" href="{{ route('account.security') }}" class="module-tab {{ request()->routeIs('account.*') ? 'module-tab-active bg-rose-600 text-white' : 'text-rose-800 hover:bg-rose-50 dark:text-rose-200 dark:hover:bg-dark-700' }}"><x-icon name="shield-check" class="size-4" /> Account</a>
                    @can('admin.system.view')
                        <a role="tab" aria-current="{{ request()->routeIs('admin.*') ? 'page' : 'false' }}" href="{{ route('admin.system.index') }}" class="module-tab {{ request()->routeIs('admin.*') ? 'module-tab-active bg-zinc-700 text-white' : 'text-zinc-800 hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-dark-700' }}"><x-icon name="server-stack" class="size-4" /> Admin</a>
                    @endcan
                </div>
            </nav>
        </x-slot:header>
        <main class="p-4 sm:p-6">
            {{ $slot }}
        </main>
    </x-layout>
    @livewireScripts
</body>
</html>
