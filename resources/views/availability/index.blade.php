@component('layouts.app')
<div class="mx-auto max-w-7xl space-y-4" x-data="availabilityExplorer(@js($filters), @js($suggestions))">
    <header><h1 class="text-2xl font-bold">Availability</h1><p class="text-sm text-gray-500">Explore up to {{ $maxDays }} days of availability.</p></header>
    <section class="rounded-lg border border-gray-200 p-4 dark:border-gray-700"><h2 class="mb-3 font-semibold">Date range and location</h2>
        <form method="GET" action="{{ route('availability.index') }}" class="flex flex-wrap items-end gap-3">
            <label class="block text-sm">From<input name="from" x-model="from" type="date" required class="mt-1 block rounded border p-2"></label>
            <label class="block text-sm">To<input name="to" x-model="to" type="date" :min="from" required class="mt-1 block rounded border p-2"></label>
            <label class="block text-sm">Location<select name="location" x-model="location" class="mt-1 block rounded border p-2"><option value="">All locations</option>@foreach($locations as $option)<option value="{{ $option['value'] }}" @selected($filters['location'] == $option['value'])>{{ $option['label'] }}</option>@endforeach</select></label>
            <input type="hidden" name="product" value="{{ $filters['product'] }}">
            <x-button type="submit" color="primary">Apply</x-button>
        </form><p class="mt-2 text-xs text-gray-500">The availability calculation is global; location filters the physical stock distribution only.</p>
    </section>
    <section class="rounded-lg border border-gray-200 p-4 dark:border-gray-700"><h2 class="mb-3 font-semibold">Find a product</h2>
        <form class="flex gap-2" @submit.prevent="search"><label class="sr-only" for="product-query">Search products</label><input id="product-query" x-model="query" class="w-full max-w-md rounded border p-2" type="search" placeholder="Name, SKU, barcode or serial"><x-button type="submit" color="primary" x-bind:disabled="loading">Search</x-button></form>
        <p x-show="error" x-text="error" class="mt-2 text-red-600" role="alert"></p>
        <ul class="mt-3 space-y-2"><template x-for="item in results" :key="item.id"><li><button type="button" class="text-primary-700 underline" @click="open(item.id)" x-text="item.name"></button> · <span x-text="item.sku || 'No SKU'"></span> · <span x-text="item.available"></span> available</li></template><li x-show="!results.length" class="text-gray-500">No products found.</li></ul>
    </section>
    @if($initial)
        <section class="rounded-lg border border-gray-200 p-4 dark:border-gray-700"><h2 class="font-semibold">{{ $initial['product']['name'] }}</h2><p class="text-sm text-gray-500">{{ $initial['product']['tracking_label'] }}</p>
            <div class="mt-3 flex flex-wrap gap-2">@foreach(['total','available','reserved','checked_out','tentative','maintenance','damaged','lost','retired'] as $bucket)<button type="button" class="rounded border px-3 py-2 text-sm capitalize" :aria-pressed="selectedBucket === '{{ $bucket }}'" @click="selectedBucket='{{ $bucket }}'">{{ str_replace('_', ' ', $bucket) }}: {{ $initial['breakdown'][$bucket] ?? 0 }}</button>@endforeach</div>
            <h3 class="mt-4 font-medium capitalize" x-text="selectedBucket.replaceAll('_',' ') + ' details'"></h3>
            @foreach($initial['breakdown']['sources'] as $bucket => $sources)<ul class="mt-2 space-y-1" x-show="selectedBucket === '{{ $bucket }}'" x-cloak>@forelse($sources as $source)<li>@if($source['url'])<a href="{{ $source['url'] }}" class="text-primary-700 underline">{{ $source['label'] }}</a>@else{{ $source['label'] }}@endif: {{ $source['quantity'] }}</li>@empty<li class="text-gray-500">No sources.</li>@endforelse</ul>@endforeach
        </section>
        <section class="rounded-lg border border-gray-200 p-4 dark:border-gray-700"><h2 class="font-semibold">Physical stock by location</h2><ul class="mt-2">@forelse($initial['locations'] as $row)<li>{{ $row['location'] }}: {{ $row['owned'] }} owned, {{ $row['serviceable'] }} serviceable</li>@empty<li class="text-gray-500">No stock at this location.</li>@endforelse</ul></section>
        <section class="overflow-x-auto rounded-lg border border-gray-200 p-4 dark:border-gray-700"><h2 class="mb-2 font-semibold">Daily availability</h2><table class="w-full text-left text-sm"><thead><tr>@foreach(['Date','Committed','Tentative','Available'] as $h)<th class="p-2">{{ $h }}</th>@endforeach</tr></thead><tbody>@foreach($initial['timeline'] as $day)<tr class="border-t border-gray-200 dark:border-gray-700"><td class="p-2">{{ $day['date'] }}</td><td>{{ $day['committed'] }}</td><td>{{ $day['tentative'] }}</td><td>{{ $day['available'] }}</td></tr>@endforeach</tbody></table></section>
    @endif
</div>
<script>
function availabilityExplorer(filters, initialResults) {
    return {
        from: filters.from, to: filters.to, location: filters.location ?? '', query: '', results: initialResults, selectedBucket: filters.bucket || 'available', loading: false, error: '',
        async search() { this.loading=true;this.error='';try {const u=new URL('{{ route('availability.search') }}');u.searchParams.set('q',this.query);u.searchParams.set('from',this.from);u.searchParams.set('to',this.to);const r=await fetch(u,{headers:{Accept:'application/json'}});if(!r.ok)throw new Error();this.results=(await r.json()).data;}catch(_){this.error='Unable to search. Check the date range and try again.';}finally{this.loading=false;}},
        open(product) { const u=new URL('{{ route('availability.index') }}');u.searchParams.set('from',this.from);u.searchParams.set('to',this.to);if(this.location)u.searchParams.set('location',this.location);if(product)u.searchParams.set('product',product);window.location.assign(u); }
    };
}
</script>
@endcomponent
