@extends('layouts.guest')

@section('title', 'Sign in')

@section('content')
    <x-card bordered shadowless>
        <h1 class="text-2xl font-semibold">Sign in</h1>
        @if ($status)
            <p class="mt-4 text-sm text-emerald-600 dark:text-emerald-400" role="status">{{ $status }}</p>
        @endif
        <form class="mt-6 space-y-4" method="POST" action="{{ route('login.store') }}">
            @csrf
            <div>
                <label class="mb-1 block text-sm font-medium" for="login">Email or username</label>
                <input class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/30 dark:border-dark-600 dark:bg-dark-700 dark:text-white" id="login" name="login" type="text" value="{{ old('login') }}" autocomplete="username webauthn" required autofocus>
                @error('login') <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium" for="password">Password</label>
                <input class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/30 dark:border-dark-600 dark:bg-dark-700 dark:text-white" id="password" name="password" type="password" autocomplete="current-password" required>
                @error('password') <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
            </div>
            <label class="flex items-center gap-2 text-sm">
                <input id="remember" name="remember" type="checkbox" value="1" @checked(old('remember'))>
                Remember me
            </label>
            <x-button type="submit" color="primary" class="w-full">Sign in</x-button>
        </form>
        @if ($passkeysEnabled)
            <div class="mt-4">
                <x-button id="passkey-login" type="button" color="secondary" class="w-full">Sign in with a passkey</x-button>
                <p id="passkey-error" class="mt-2 hidden text-sm text-red-600" role="alert"></p>
                <p id="passkey-unavailable" class="mt-2 hidden text-xs text-gray-500">Passkeys are unavailable in this browser or on this connection.</p>
            </div>
        @endif
        <a href="{{ route('password.request') }}" class="st-link mt-4 inline-block text-sm">Forgot your password?</a>
    </x-card>
@endsection

@if ($passkeysEnabled)
    @push('scripts')
        @vite('resources/js/auth/passkey-login.ts')
    @endpush
@endif
