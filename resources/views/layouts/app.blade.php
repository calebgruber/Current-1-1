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
<body class="st-app st-form-controls min-h-screen font-sans antialiased" x-cloak>
    @include('layouts.partials.page-loader')
    @php
        $settings = app(\App\Domain\Settings\Services\SettingsRepository::class);
        $modules = app(\App\Support\Navigation\ModuleNavigation::class)->for(auth()->user(), request());
        $current = collect($modules)->firstWhere('active', true);
    @endphp
    <x-dialog />
    <x-toast />

    <header class="st-topbar">
        <div class="st-shell flex flex-wrap items-center gap-3 py-3">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-2 font-bold text-ink">
                <span class="grid size-9 place-items-center rounded-lg bg-gradient-to-br from-indigo-500 via-violet-500 to-fuchsia-500 text-sm font-black text-white shadow">ST</span>
                <span class="truncate">{{ $settings->get('general.organization_name', config('app.name', 'StageTrack')) }}</span>
            </a>
            <div class="ms-auto flex items-center gap-2">
                <a href="{{ route('search.index') }}" class="st-icon-btn" title="Search (⌘K)" aria-label="Search">
                    <x-icon name="magnifying-glass" class="size-5" />
                </a>
                <div class="st-icon-btn" title="Toggle light / dark mode">
                    <x-theme-switch simple only-icons />
                </div>
                <a href="{{ route('account.profile') }}" class="hidden items-center gap-2 rounded-lg px-2 py-1.5 text-sm font-semibold text-ink hover:bg-hover sm:inline-flex">
                    <span class="grid size-8 place-items-center rounded-full bg-violet-600 text-xs font-bold text-white">{{ \Illuminate\Support\Str::of(auth()->user()->name)->explode(' ')->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('') }}</span>
                    {{ auth()->user()->name }}
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="st-icon-btn text-red-600 dark:text-red-400" title="Sign out" aria-label="Sign out">
                        <x-icon name="arrow-right-start-on-rectangle" class="size-5" />
                    </button>
                </form>
            </div>
        </div>

        <nav aria-label="Modules" class="st-shell pb-3">
            <ul class="st-module-rail">
                @foreach ($modules as $module)
                    <li>
                        <a href="{{ $module['href'] }}" style="--mod: {{ $module['color'] }}"
                           class="st-module {{ $module['active'] ? 'is-active' : '' }}"
                           @if ($module['active']) aria-current="page" @endif>
                            <span class="st-module__icon"><x-icon :name="$module['icon']" class="size-5" /></span>
                            <span class="truncate">{{ $module['label'] }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>
    </header>

    @if ($current)
        <nav aria-label="{{ $current['label'] }} pages" class="st-subnav" style="--mod: {{ $current['color'] }}">
            <div class="st-shell flex flex-wrap items-center gap-x-1 gap-y-2 py-2">
                <span class="me-2 hidden items-center gap-2 text-sm font-bold md:inline-flex" style="color: var(--mod)">
                    <x-icon :name="$current['icon']" class="size-5" /> {{ $current['label'] }}
                </span>
                @foreach (collect($current['tabs'])->where('action', false) as $tab)
                    <a href="{{ $tab['href'] }}" class="st-subtab {{ $tab['active'] ? 'is-active' : '' }}" @if ($tab['active']) aria-current="page" @endif>
                        <x-icon :name="$tab['icon']" class="size-4" /> {{ $tab['label'] }}
                    </a>
                @endforeach
                <div class="ms-auto flex flex-wrap gap-2">
                    @foreach (collect($current['tabs'])->where('action', true) as $tab)
                        <a href="{{ $tab['href'] }}" class="st-action {{ $tab['active'] ? 'is-active' : '' }}" @if ($tab['active']) aria-current="page" @endif>
                            <x-icon :name="$tab['icon']" class="size-4" /> {{ $tab['label'] }}
                        </a>
                    @endforeach
                </div>
            </div>
        </nav>
    @endif

    <main class="st-shell py-6" @if ($current) style="--mod: {{ $current['color'] }}" @endif>
        {{ $slot }}
    </main>
    @livewireScripts
    @stack('scripts')
</body>
</html>
