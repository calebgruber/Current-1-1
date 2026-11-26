@component('layouts.app')
<div class="mx-auto max-w-7xl space-y-4">
    <header class="flex flex-wrap items-end justify-between gap-4"><div><h1 class="text-2xl font-bold">{{ $document['number'] }} · {{ $document['title'] }}</h1><p class="text-sm text-gray-500">{{ $document['type_label'] }} · {{ $document['entity_label'] }}</p></div><div class="flex flex-wrap gap-2"><x-button :href="route('documents.index')" color="secondary">Documents</x-button><a class="rounded border px-3 py-2" href="{{ route('documents.pdf', $document['id']) }}">View PDF</a>@can('documents.documents.export')<a class="rounded border px-3 py-2" href="{{ route('documents.pdf', ['document' => $document['id'], 'download' => 1]) }}">Download PDF</a>@endcan @can('documents.documents.delete')<form method="POST" action="{{ route('documents.destroy', $document['id']) }}" onsubmit="return confirm('Delete this document?')">@csrf @method('DELETE')<x-button type="submit" color="red">Delete</x-button></form>@endcan</div></header>
    @can('documents.documents.create')
    <form method="POST" action="{{ route('documents.update', $document['id']) }}" class="flex flex-wrap items-end gap-3 rounded-lg border border-gray-200 p-4 dark:border-gray-700">@csrf @method('PUT')
        <label class="grow text-sm">Title<input name="title" value="{{ old('title', $document['title']) }}" required maxlength="255" class="mt-1 block w-full rounded border p-2"></label>
        <div class="w-full">@include('partials.custom-fields', ['customFieldSchema' => $customFieldSchema, 'customFields' => $customFields])</div>
        <x-button type="submit" color="primary">Save document</x-button>
        @error('title')<p class="w-full text-red-600">{{ $message }}</p>@enderror
    </form>
    @endcan
    <iframe title="Document preview" src="{{ route('documents.html', $document['id']) }}" sandbox="" class="h-[75vh] w-full rounded border bg-white"></iframe>
</div>
@endcomponent
