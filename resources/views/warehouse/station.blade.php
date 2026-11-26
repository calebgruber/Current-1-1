@component('layouts.app')
<div class="mx-auto max-w-7xl space-y-5">
    <div class="flex flex-wrap items-end justify-between gap-4"><div><h1 class="text-2xl font-bold">{{ $modeLabel }} station</h1></div><x-button :href="route('warehouse.index')" color="secondary">Warehouse</x-button></div>
    <nav class="flex flex-wrap gap-3" aria-label="Warehouse stations">@foreach($stations->where('allowed') as $s)<a class="text-primary-700 underline" href="{{ $job ? route('warehouse.'.$s['slug'], $job['id']) : route('warehouse.'.$s['slug']) }}">{{ $s['label'] }}</a>@endforeach</nav>
    <section class="space-y-3 rounded-lg border border-gray-200 p-4 dark:border-gray-700" x-data="warehouseStation('{{ $station }}', '{{ $mode }}', {{ $job?->id ?? 'null' }}, '{{ route('warehouse.'.$station) }}', '{{ route('warehouse.'.$station, ['job' => '__JOB__']) }}')">
        <label class="block text-sm">Find a job
            <input class="mt-1 block w-full max-w-xl rounded border border-gray-300 p-2 dark:bg-dark-700" type="search" x-model="query" @input.debounce.250ms="lookup" @keydown.enter.prevent="lookup" placeholder="Search jobs" aria-label="Find a job">
        </label>
        <ul x-show="options.length" class="max-h-48 max-w-xl overflow-auto rounded border border-gray-300" x-cloak>
            <template x-for="option in options" :key="option.value"><li><button type="button" class="w-full p-2 text-left hover:bg-gray-100 dark:hover:bg-dark-700" @click="select(option.value)" x-text="option.label"></button></li></template>
        </ul>
        @if($job)<p>{{ $job['number'] }} · {{ $job['name'] }} — {{ $job['stage_label'] }}</p>@else<p>No job selected.</p>@endif
        @if($can['scan'])
            <form class="flex flex-wrap items-end gap-3" @submit.prevent="scan">
                <label class="block text-sm">Barcode or job number<input x-ref="code" x-model="code" autofocus required class="mt-1 block rounded border border-gray-300 p-2" placeholder="Scan barcode or job number"></label>
                <label class="block text-sm">Quantity<input x-model="quantity" type="number" min="1" max="1000" required class="mt-1 block w-24 rounded border border-gray-300 p-2"></label>
                <x-button type="submit" color="primary" x-bind:disabled="pending">Scan</x-button>
            </form>
        @else<p>Read-only station; scan permission required.</p>@endif
        <p x-show="message" x-text="message" role="status" aria-live="polite"></p>
        <p x-show="error" x-text="error" role="alert" class="text-red-600"></p>
    </section>
    @if($job)
        <section class="overflow-x-auto rounded-lg border border-gray-200 p-4 dark:border-gray-700"><h2 class="mb-2 font-semibold">Progress</h2>@if(!$lines)<p>No equipment allocated.</p>@else<table class="w-full text-left text-sm"><thead><tr>@foreach(['Product','Pull','Pick','Prep','Stage','Out','Returned'] as $heading)<th class="p-2">{{ $heading }}</th>@endforeach</tr></thead><tbody id="warehouse-lines">@foreach($lines as $line)<tr class="border-t border-gray-200 dark:border-gray-700"><td class="py-2">{{ $line['product'] }} {{ $line['sku'] }}</td><td>{{ $line['pull'] }}</td><td>{{ $line['done']['pick'] }}</td><td>{{ $line['done']['prep'] }}</td><td>{{ $line['done']['stage'] }}</td><td>{{ $line['done']['checkout'] }}</td><td>{{ $line['done']['return'] }}</td></tr>@endforeach</tbody></table>@endif</section>
    @endif
</div>
<script>
function warehouseStation(station, mode, jobId, baseRoute, jobRoute) {
    return {
        station, mode, jobId, query: '', options: [], code: '', quantity: 1, pending: false, message: '', error: '',
        async lookup() {
            try {
                const response = await fetch('{{ route('jobs.lookup', ['type' => 'jobs', 'warehouse' => 1]) }}&q=' + encodeURIComponent(this.query), {headers:{Accept:'application/json'}});
                this.options = response.ok ? await response.json() : [];
            } catch (_) { this.options = []; }
        },
        select(id) { window.location.assign(id ? jobRoute.replace('__JOB__', encodeURIComponent(id)) : baseRoute); },
        async scan() {
            if (this.pending || !this.code.trim()) return;
            this.pending = true; this.error = '';
            try {
                const response = await fetch('{{ route('warehouse.scan') }}', {method:'POST', credentials:'same-origin', headers:{'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content}, body:JSON.stringify({mode:this.mode,code:this.code,quantity:this.quantity,job_id:this.jobId,station:this.station})});
                const data = await response.json();
                if (!response.ok) throw new Error(Object.values(data.errors || {}).flat().join(' ') || data.message || 'Scan failed.');
                this.message = data.result.title + ': ' + data.result.message; this.code = '';
                if (data.select_job) { this.select(data.select_job.id); return; }
                if (data.progress) window.location.reload();
                this.$refs.code.focus();
            } catch (e) { this.error = e.message || 'Scan failed.'; }
            finally { this.pending = false; }
        }
    };
}
</script>
@endcomponent
