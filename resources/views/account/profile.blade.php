@component('layouts.app')
<div class="mx-auto max-w-3xl space-y-5">
    <h1 class="text-2xl font-bold">Profile</h1>
    @if(session('success'))<x-alert color="green">{{ session('success') }}</x-alert>@endif
    <x-card bordered shadowless>
        <form method="POST" action="{{ route('account.profile.update') }}" class="max-w-xl space-y-4">
            @csrf @method('PUT')
            @foreach(['name' => 'Name', 'username' => 'Username', 'email' => 'Email'] as $name => $label)
                <label class="block text-sm">{{ $label }} <x-input name="{{ $name }}" type="{{ $name === 'email' ? 'email' : 'text' }}" value="{{ old($name, $profile[$name]) }}" @required($name !== 'username') />@error($name)<span class="text-red-600">{{ $message }}</span>@enderror</label>
            @endforeach
            <label class="block text-sm">Timezone <select name="timezone" class="mt-1 block w-full rounded border-gray-300"><option value="">Organization default</option>@foreach($timezones as $timezone)<option value="{{ $timezone }}" @selected(old('timezone', $profile['timezone']) === $timezone)>{{ $timezone }}</option>@endforeach</select>@error('timezone')<span class="text-red-600">{{ $message }}</span>@enderror</label>
            <label class="block text-sm">Theme <select name="theme" class="mt-1 block w-full rounded border-gray-300">@foreach(['system' => 'System', 'light' => 'Light', 'dark' => 'Dark'] as $value => $label)<option value="{{ $value }}" @selected(old('theme', $profile['theme']) === $value)>{{ $label }}</option>@endforeach</select>@error('theme')<span class="text-red-600">{{ $message }}</span>@enderror</label>
            <label class="flex items-center gap-2 text-sm"><input type="hidden" name="reduce_motion" value="0"><input type="checkbox" name="reduce_motion" value="1" @checked(old('reduce_motion', $profile['reduce_motion']))> Reduce motion</label>
            <label class="flex items-center gap-2 text-sm"><input type="hidden" name="warehouse_sounds" value="0"><input type="checkbox" name="warehouse_sounds" value="1" @checked(old('warehouse_sounds', $profile['warehouse_sounds']))> Warehouse sounds</label>
            <x-button type="submit" color="primary">Save profile</x-button>
        </form>
    </x-card>
</div>
@endcomponent
