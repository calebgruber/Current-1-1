<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" x-data="tallstackui_darkTheme()">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="light dark">
    <title>@yield('title', 'Setup · '.config('app.name', 'StageTrack Enterprise'))</title>
    <tallstackui:script />
    @livewireStyles
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-white font-sans antialiased dark:bg-dark-800" x-cloak>
    <div class="flex min-h-screen flex-col">
        <header class="flex min-h-14 items-center border-b border-gray-200 px-6 py-3 dark:border-dark-600">
            <div class="flex items-center gap-2 font-semibold">
                <span class="grid size-7 place-items-center rounded-md bg-primary-600 text-xs font-bold text-white">ST</span>
                {{ config('app.name', 'StageTrack Enterprise') }} · Setup
            </div>
        </header>
        <div class="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-8 px-4 py-8 md:flex-row md:px-6 md:py-10">
            @yield('content')
        </div>
    </div>
    @livewireScripts
</body>
</html>
