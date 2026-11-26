@component('layouts.app')
<div class="mx-auto max-w-4xl space-y-5">
    <div class="flex items-end justify-between gap-3"><h1 class="text-2xl font-bold">{{ $record->reference }} — {{ $record->title }}</h1>@can('maintenance.records.edit')<x-button :href="route('maintenance.edit', $record)" color="secondary">Edit</x-button>@endcan</div>
    <x-card bordered shadowless>
        <dl class="grid gap-4 sm:grid-cols-2">
            <div><dt class="text-gray-500">Product</dt><dd>{{ $record->product?->name ?? '—' }}</dd></div><div><dt class="text-gray-500">Asset / quantity</dt><dd>{{ $record->asset?->asset_number ?? $record->quantity }}</dd></div>
            <div><dt class="text-gray-500">Status</dt><dd>{{ $record->status->label() }}</dd></div><div><dt class="text-gray-500">Type / priority</dt><dd class="capitalize">{{ str_replace('_', ' ', $record->type) }} / {{ $record->priority }}</dd></div>
            <div><dt class="text-gray-500">Assigned to</dt><dd>{{ $record->assignee?->name ?? '—' }}</dd></div><div><dt class="text-gray-500">Reported by</dt><dd>{{ $record->reporter?->name ?? '—' }}</dd></div>
            <div><dt class="text-gray-500">Job</dt><dd>{{ $record->job ? $record->job->number.' — '.$record->job->name : '—' }}</dd></div><div><dt class="text-gray-500">Cost</dt><dd>{{ $record->cost ?? '—' }}</dd></div>
            <div><dt class="text-gray-500">Due</dt><dd>{{ $record->due_at?->format('M j, Y g:i A') ?? '—' }}</dd></div><div><dt class="text-gray-500">Started</dt><dd>{{ $record->started_at?->format('M j, Y g:i A') ?? '—' }}</dd></div><div><dt class="text-gray-500">Completed</dt><dd>{{ $record->completed_at?->format('M j, Y g:i A') ?? '—' }}</dd></div>
        </dl>
        @unless($record->asset_id)<p class="mt-4 text-sm text-gray-500">This record does not make bulk stock unavailable. Adjust stock quantities separately in inventory.</p>@endunless
        <div class="mt-4"><h2 class="font-semibold">Description</h2><p class="whitespace-pre-wrap">{{ $record->description ?: '—' }}</p></div><div class="mt-4"><h2 class="font-semibold">Resolution</h2><p class="whitespace-pre-wrap">{{ $record->resolution ?: '—' }}</p></div>
    </x-card>
</div>
@endcomponent
