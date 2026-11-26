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
    <x-layout>
        <x-slot:top>
            <x-dialog />
            <x-toast />
        </x-slot:top>
        <x-slot:header>
            <x-layout.header>
                <x-slot:right>
                    <span class="font-semibold text-primary-600 dark:text-primary-400">{{ auth()->user()->name }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <x-button type="submit" color="secondary">Sign out</x-button>
                    </form>
                </x-slot:right>
            </x-layout.header>
        </x-slot:header>
        <x-slot:menu>
            <x-side-bar smart collapsible>
                <x-slot:brand>
                    <div class="my-4 px-4 text-lg font-bold text-primary-600 dark:text-primary-400">StageTrack</div>
                </x-slot:brand>
                <x-side-bar.item text="Dashboard" icon="home" :route="route('dashboard')" />
                @can('jobs.jobs.view')
                    <x-side-bar.item text="Jobs" icon="briefcase" :route="route('jobs.index')" />
                @endcan
                @can('inventory.products.view')
                    <x-side-bar.item text="Inventory" icon="archive-box" :route="route('inventory.products.index')" />
                @endcan
                @can('inventory.categories.view')
                    <x-side-bar.item text="Categories" icon="tag" :route="route('inventory.categories.index')" />
                @endcan
                @can('inventory.locations.view')
                    <x-side-bar.item text="Locations" icon="map-pin" :route="route('inventory.locations.index')" />
                @endcan
                @can('assets.assets.view')
                    <x-side-bar.item text="Assets" icon="cube" :route="route('assets.index')" />
                @endcan
                @can('maintenance.records.view')
                    <x-side-bar.item text="Maintenance" icon="wrench-screwdriver" :route="route('maintenance.index')" />
                @endcan
                @can('inventory.availability.view')
                    <x-side-bar.item text="Availability" icon="calendar-days" :route="route('availability.index')" />
                @endcan
                @can('reservations.reservations.view')
                    <x-side-bar.item text="Reservations" icon="calendar" :route="route('reservations.index')" />
                @endcan
                @canany(['warehouse.picking.view', 'warehouse.checkout.view'])
                    <x-side-bar.item text="Warehouse" icon="qr-code" :route="route('warehouse.index')" />
                @endcanany
                @can('contacts.contacts.view')
                    <x-side-bar.item text="Contacts" icon="users" :route="route('contacts.index')" />
                @endcan
                @can('documents.documents.view')
                    <x-side-bar.item text="Documents" icon="document-text" :route="route('documents.index')" />
                @endcan
                @can('documents.templates.view')
                    <x-side-bar.item text="Templates" icon="rectangle-stack" :route="route('documents.templates.index')" />
                @endcan
                @can('reporting.reports.view')
                    <x-side-bar.item text="Reports" icon="chart-bar" :route="route('reports.index')" />
                @endcan
                @can('rules.rules.view')
                    <x-side-bar.item text="Rules" icon="adjustments-horizontal" :route="route('rules.index')" />
                @endcan
                <x-side-bar.item text="Search" icon="magnifying-glass" :route="route('search.index')" />
                @can('settings.custom_fields.view')
                    <x-side-bar.item text="Custom fields" icon="list-bullet" :route="route('settings.custom-fields.index')" />
                @endcan
                @can('admin.system.view')
                    <x-side-bar.item text="System" icon="cog-6-tooth" :route="route('admin.system.index')" />
                @endcan
                <x-side-bar.item text="My profile" icon="user" :route="route('account.profile')" />
                <x-side-bar.item text="Security" icon="shield-check" :route="route('account.security')" />
            </x-side-bar>
        </x-slot:menu>
        <main class="p-4 sm:p-6">
            {{ $slot }}
        </main>
    </x-layout>
    @livewireScripts
</body>
</html>
