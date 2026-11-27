<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" x-data="tallstackui_darkTheme()">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="light dark">
    <title>@yield('title', config('app.name', 'StageTrack Enterprise'))</title>
    <tallstackui:script />
    @livewireStyles
    @vite(['resources/css/app.css'])
</head>
<body class="st-form-controls min-h-screen bg-white font-sans antialiased dark:bg-dark-800" x-cloak>
    <div class="flex min-h-screen flex-col items-center justify-center px-4 py-12">
        <div class="mb-8 flex items-center gap-2 text-lg font-semibold">
            <span class="grid size-8 place-items-center rounded-lg bg-primary-600 text-sm font-bold text-white">ST</span>
            {{ \App\Support\Installation::isInstalled() ? app(\App\Domain\Settings\Services\SettingsRepository::class)->get('general.organization_name', config('app.name', 'StageTrack')) : config('app.name', 'StageTrack') }}
        </div>
        <main class="w-full max-w-sm">
            <x-dialog />
            <x-toast />
            @yield('content')
        </main>
    </div>
    @livewireScripts
    @stack('scripts')
</body>
</html>
