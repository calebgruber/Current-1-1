@extends('layouts.guest')

@section('title', 'Choose a new password')

@section('content')
    <x-card bordered shadowless>
        <h1 class="text-2xl font-semibold">Choose a new password</h1>
        <form class="mt-6 space-y-4" method="POST" action="{{ route('password.update') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <div>
                <label class="mb-1 block text-sm font-medium" for="email">Email</label>
                <input class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/30 dark:border-dark-600 dark:bg-dark-700 dark:text-white" id="email" name="email" type="email" value="{{ old('email', $email) }}" autocomplete="email" required>
                @error('email') <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium" for="password">New password</label>
                <input class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/30 dark:border-dark-600 dark:bg-dark-700 dark:text-white" id="password" name="password" type="password" autocomplete="new-password" required>
                @error('password') <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium" for="password_confirmation">Confirm password</label>
                <input class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/30 dark:border-dark-600 dark:bg-dark-700 dark:text-white" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
            </div>
            <x-button type="submit" color="primary" class="w-full">Reset password</x-button>
        </form>
    </x-card>
@endsection
