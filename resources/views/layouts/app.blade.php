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
                @can('assets.assets.view')
                    <x-side-bar.item text="Assets" icon="cube" :route="route('assets.index')" />
                @endcan
                @can('maintenance.records.view')
                    <x-side-bar.item text="Maintenance" icon="wrench-screwdriver" :route="route('maintenance.index')" />
                @endcan
                @can('warehouse.picking.view')
                    <x-side-bar.item text="Warehouse" icon="qr-code" :route="route('warehouse.index')" />
                @endcan
                <x-side-bar.item text="Search" icon="magnifying-glass" :route="route('search.index')" />
            </x-side-bar>
        </x-slot:menu>
        <main class="p-4 sm:p-6">
            {{ $slot }}
        </main>
    </x-layout>
    @livewireScripts
</body>
</html>
