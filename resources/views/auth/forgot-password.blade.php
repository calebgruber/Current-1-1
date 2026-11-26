@extends('layouts.guest')

@section('title', 'Reset password')

@section('content')
    <x-card bordered shadowless>
        <h1 class="text-2xl font-semibold">Forgot your password?</h1>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">Enter your email address and we'll send a reset link if an account exists.</p>
        @if ($status)
            <p class="mt-4 text-sm text-emerald-600 dark:text-emerald-400" role="status">{{ $status }}</p>
        @endif
        <form class="mt-6 space-y-4" method="POST" action="{{ route('password.email') }}">
            @csrf
            <div>
                <label class="mb-1 block text-sm font-medium" for="email">Email</label>
                <input class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/30 dark:border-dark-600 dark:bg-dark-700 dark:text-white" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus>
                @error('email') <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
            </div>
            <x-button type="submit" color="primary" class="w-full">Send reset link</x-button>
        </form>
        <a href="{{ route('login') }}" class="st-link mt-4 inline-block text-sm">Back to sign in</a>
    </x-card>
@endsection
