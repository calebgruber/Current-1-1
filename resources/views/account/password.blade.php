@component('layouts.app')
<div class="mx-auto max-w-3xl space-y-5">
    <h1 class="text-2xl font-bold">Change password</h1>
    <x-card bordered shadowless>
        @if($expired)<p class="mb-4 text-sm text-red-600">Your password has expired.</p>@endif
        <p class="mb-4 text-sm text-gray-500">Use at least {{ $policy['min_length'] }} characters@if($policy['mixed']), including upper and lower case letters and numbers@endif.</p>
        <form method="POST" action="{{ route('account.password.update') }}" class="max-w-xl space-y-4">
            @csrf @method('PUT')
            <label class="block text-sm">Current password <x-input name="current_password" type="password" autocomplete="current-password" required />@error('current_password')<span class="text-red-600">{{ $message }}</span>@enderror</label>
            <label class="block text-sm">New password <x-input name="password" type="password" autocomplete="new-password" required />@error('password')<span class="text-red-600">{{ $message }}</span>@enderror</label>
            <label class="block text-sm">Confirm new password <x-input name="password_confirmation" type="password" autocomplete="new-password" required /></label>
            <div class="flex gap-2"><x-button type="submit" color="primary">Update password</x-button><x-button :href="route('account.security')" color="secondary">Cancel</x-button></div>
        </form>
    </x-card>
</div>
@endcomponent
