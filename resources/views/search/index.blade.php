@component('layouts.app')
<div class="mx-auto max-w-7xl space-y-4">
    <header><h1 class="text-2xl font-bold">Search</h1><p class="text-sm text-gray-500">Inventory, serial numbers, barcodes, jobs, contacts, maintenance, documents, reports and locations.</p></header>
    <form id="search-form" method="GET" action="{{ route('search.index') }}" class="space-y-3">
        <label class="sr-only" for="search-term">Search</label><input id="search-term" name="q" value="{{ $q }}" class="w-full max-w-xl rounded border p-2" type="search" placeholder="Search or scan a barcode…" autofocus>
        <input type="hidden" name="type" value="{{ $type }}">
        <div class="flex flex-wrap gap-2"><a class="rounded-full px-3 py-1 text-xs {{ !$type ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-700' }}" href="{{ route('search.index', ['q' => $q]) }}">All</a>@foreach($types as $t)<a class="rounded-full px-3 py-1 text-xs {{ $type === $t['value'] ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-700' }}" href="{{ route('search.index', ['q' => $q, 'type' => $t['value']]) }}">{{ $t['label'] }}</a>@endforeach</div>
    </form>
    @if($q === '')<div class="rounded-lg border border-gray-200 p-6 text-gray-500">Start typing to search. Tip: press ⌘K / Ctrl K from anywhere.</div>
    @elseif(!$results)<div class="rounded-lg border border-gray-200 p-6 text-gray-500">No results for “{{ $q }}”</div>
    @else
        @foreach(collect($results)->groupBy('type_label') as $label => $items)<section class="overflow-hidden rounded-lg border border-gray-200"><h2 class="border-b p-3 font-semibold">{{ $label }}</h2><ul class="divide-y divide-gray-200">@foreach($items as $item)<li><a href="{{ $item['url'] }}" class="m-2 flex items-center gap-3 rounded border border-gray-200 px-4 py-2.5 hover:bg-gray-50"><span class="min-w-0 flex-1"><span class="block truncate text-sm font-medium">{{ $item['title'] }}</span>@if($item['subtitle'])<span class="block truncate text-xs text-gray-500">{{ $item['subtitle'] }}</span>@endif</span>@if($item['exact'])<span class="rounded bg-green-100 px-2 py-1 text-xs text-green-800">Exact match</span>@endif</a></li>@endforeach</ul></section>@endforeach
    @endif
</div>
<script>
(() => { const input=document.getElementById('search-term'), form=document.getElementById('search-form');let timer;input?.addEventListener('input',()=>{clearTimeout(timer);timer=setTimeout(()=>form.requestSubmit(),250);}); })();
</script>
@endcomponent
