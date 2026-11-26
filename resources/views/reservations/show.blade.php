@component('layouts.app')
<div class="mx-auto max-w-4xl space-y-5">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div><h1 class="text-2xl font-bold">{{ $reservation['label'] }}</h1><p class="mt-1"><span class="rounded border px-2 py-1 text-xs">{{ ucfirst($reservation['status']) }}</span></p></div>
        <div class="flex flex-wrap gap-3"><a class="text-primary-700 underline" href="{{ route('reservations.index') }}">All reservations</a>@if($can['edit'])<a class="text-primary-700 underline" href="{{ route('reservations.edit', $reservation['id']) }}">Edit</a>@endif @if($can['delete'])<form method="POST" action="{{ route('reservations.destroy', $reservation['id']) }}" onsubmit="return confirm('Delete this reservation?')">@csrf @method('DELETE')<button class="text-primary-700 underline" type="submit">Delete</button></form>@endif</div>
    </div>
    @if(session('success'))<x-alert color="green">{{ session('success') }}</x-alert>@endif
    @if(session('error'))<x-alert color="red">{{ session('error') }}</x-alert>@endif
    <x-card bordered shadowless>
        <div class="space-y-3">
            <p>{{ $reservation['product'] ?: 'Unknown product' }} · {{ $reservation['total'] }} total</p>
            <p><time data-format="datetime" datetime="{{ $reservation['starts_at'] }}">{{ \Illuminate\Support\Carbon::parse($reservation['starts_at'])->format('M j, Y g:i A') }}</time> – <time data-format="datetime" datetime="{{ $reservation['ends_at'] }}">{{ \Illuminate\Support\Carbon::parse($reservation['ends_at'])->format('M j, Y g:i A') }}</time></p>
            @if($reservation['notes'])<p class="whitespace-pre-wrap">{{ $reservation['notes'] }}</p>@endif
            @if($reservation['job'])<p>Job: {{ $reservation['job']['number'] }} · {{ $reservation['job']['name'] }}</p>@endif
            @if($reservation['job_generated'])<p class="text-sm text-gray-600">This reservation is managed by its job allocation.</p>@endif
            @if($conflict && $conflict['message'])<p class="text-sm text-amber-700">{{ $conflict['message'] }}</p>@endif
            @include('partials.custom-field-display', ['customFieldSchema' => $customFieldSchema, 'customFields' => $customFields])
            @if($can['approve'] && $reservation['status'] !== 'confirmed')
                <form method="POST" action="{{ route('reservations.confirm', $reservation['id']) }}" class="space-y-2 border-t pt-4">
                    @csrf
                    @if($can['override'])<label class="flex items-center gap-2"><input id="confirm-override" type="checkbox" name="override" value="1" @checked(old('override'))> Override overbooking</label><label id="confirm-override-reason" class="{{ old('override') ? '' : 'hidden' }}">Reason <x-input name="override_reason" value="{{ old('override_reason') }}" minlength="5" maxlength="500" /></label>@endif
                    @error('override_reason')<p role="alert" class="text-sm text-red-600">{{ $message }}</p>@enderror
                    @error('quantity')<p role="alert" class="text-sm text-red-600">{{ $message }}</p>@enderror
                    <x-button type="submit" color="primary">Confirm</x-button>
                </form>
            @endif
            @if(($can['edit'] || $can['approve']) && $reservation['status'] !== 'released')
                <form method="POST" action="{{ route('reservations.release', $reservation['id']) }}" onsubmit="return confirm('Release this reservation?')">@csrf<x-button type="submit" color="secondary">Release</x-button></form>
            @endif
        </div>
    </x-card>
</div>
<script>
(() => {
    document.querySelectorAll('time[data-format="datetime"]').forEach((time) => {
        const date = new Date(time.dateTime);
        if (!Number.isNaN(date.getTime())) time.textContent = new Intl.DateTimeFormat(undefined, {dateStyle: 'medium', timeStyle: 'short'}).format(date);
    });
    const checkbox = document.getElementById('confirm-override');
    const reason = document.getElementById('confirm-override-reason');
    if (checkbox && reason) {
        const update = () => { reason.classList.toggle('hidden', !checkbox.checked); reason.querySelector('input').required = checkbox.checked; };
        checkbox.addEventListener('change', update); update();
    }
})();
</script>
@endcomponent
