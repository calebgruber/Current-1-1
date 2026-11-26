@extends('layouts.guest')

@section('title', 'Two-factor verification')

@section('content')
    @php($recoveryMode = $errors->has('recovery_code'))
    <x-card bordered shadowless>
        <h1 class="text-2xl font-semibold">Two-factor verification</h1>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">Enter a code from your authenticator app or use a recovery code.</p>
        <form class="mt-6 space-y-4" method="POST" action="{{ route('two-factor.verify') }}">
            @csrf
            <div id="authenticator-field" @class(['hidden' => $recoveryMode])>
                <label class="mb-1 block text-sm font-medium" for="code">Authenticator code</label>
                <input class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/30 dark:border-dark-600 dark:bg-dark-700 dark:text-white" id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" @disabled($recoveryMode) @required(! $recoveryMode) autofocus>
                @error('code') <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
            </div>
            <div id="recovery-field" @class(['hidden' => ! $recoveryMode])>
                <label class="mb-1 block text-sm font-medium" for="recovery_code">Recovery code</label>
                <input class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/30 dark:border-dark-600 dark:bg-dark-700 dark:text-white" id="recovery_code" name="recovery_code" type="text" autocomplete="off" @disabled(! $recoveryMode) @required($recoveryMode)>
                @error('recovery_code') <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
            </div>
            <x-button type="submit" color="primary" class="w-full">Verify</x-button>
        </form>
        <button id="toggle-recovery" class="mt-4 text-sm text-primary-700 dark:text-primary-300" type="button">{{ $recoveryMode ? 'Use an authenticator code' : 'Use a recovery code' }}</button>
        <div class="mt-3"><a href="{{ route('login') }}" class="st-link text-sm">Back to sign in</a></div>
    </x-card>
@endsection

@push('scripts')
    <script>
        document.getElementById('toggle-recovery').addEventListener('click', function () {
            const recovery = document.getElementById('recovery-field');
            const authenticator = document.getElementById('authenticator-field');
            const isRecovery = recovery.classList.toggle('hidden') === false;

            authenticator.classList.toggle('hidden', isRecovery);
            document.getElementById('code').disabled = isRecovery;
            document.getElementById('code').required = !isRecovery;
            document.getElementById('recovery_code').disabled = !isRecovery;
            document.getElementById('recovery_code').required = isRecovery;
            this.textContent = isRecovery ? 'Use an authenticator code' : 'Use a recovery code';
        });
    </script>
@endpush
