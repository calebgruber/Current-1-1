@extends('layouts.guest')

@section('title', 'Confirm password')

@section('content')
    <x-card bordered shadowless>
        <h1 class="text-2xl font-semibold">Confirm your password</h1>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">Please confirm your password before continuing.</p>
        <form class="mt-6 space-y-4" method="POST" action="{{ route('password.confirm.store') }}">
            @csrf
            <x-input name="password" type="password" label="Password" autocomplete="current-password" required autofocus />
            @error('password') <p class="text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
            <x-button type="submit" color="primary" class="w-full">Confirm</x-button>
        </form>
    </x-card>
@endsection
