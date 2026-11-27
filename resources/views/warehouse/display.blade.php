<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><meta name="color-scheme" content="dark"><title>Warehouse display · {{ config('app.name') }}</title><tallstackui:script />@livewireStyles @vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body class="st-form-controls min-h-screen bg-zinc-950 text-zinc-50">
    @include('layouts.partials.page-loader')
<main class="mx-auto max-w-7xl space-y-6 p-4 sm:p-6" x-data="warehouseDisplay({{ $job['id'] ?? 'null' }}, '{{ $initialMode }}')">
    <header class="flex flex-wrap items-center justify-between gap-4"><h1 class="text-3xl font-semibold">Warehouse display</h1><button type="button" class="rounded border border-zinc-700 px-3 py-2" @click="fullscreen">Fullscreen</button><a class="rounded border border-zinc-700 px-3 py-2" href="{{ route('dashboard') }}">Exit display</a></header>
    <div class="flex flex-wrap gap-3"><label>Job <select class="rounded border p-2 dark:bg-dark-700" @change="changeJob"><option value="">Select a job</option>@foreach($jobs as $j)<option value="{{ $j['id'] }}" @selected(($job['id'] ?? null) === $j['id'])>{{ $j['number'] }} · {{ $j['name'] }}</option>@endforeach @if($job && !collect($jobs)->contains('id', $job['id']))<option value="{{ $job['id'] }}" selected>{{ $job['number'] }} · {{ $job['name'] }}</option>@endif</select></label>
        <label>Mode <select x-model="mode" class="rounded border p-2 dark:bg-dark-700">@foreach($modes as $m)<option value="{{ $m['value'] }}">{{ $m['label'] }}{{ $m['allowed'] ? '' : ' (read only)' }}</option>@endforeach</select></label>
    </div>
    @if($job)<h2 id="warehouse-job-header" class="text-2xl">{{ $job['number'] }} · {{ $job['name'] }} — {{ $job['stage_label'] }}</h2><p id="warehouse-totals">Pull {{ $totals['pull'] }} · Picked {{ $totals['pick'] }} · Out {{ $totals['checkout'] }} · Returned {{ $totals['return'] }}</p>@else<p>Select or scan a job to begin.</p>@endif
    <form class="flex flex-wrap gap-3" x-show="canScan" @submit.prevent="scan"><input x-model="code" class="rounded border p-3 dark:bg-dark-700" placeholder="Scan barcode or job number" aria-label="Barcode" required><button class="rounded bg-primary-600 px-4 py-2 text-white" :disabled="pending">Scan</button></form>
    <p x-show="!canScan">Read-only mode; scan permission required.</p><p x-show="message" x-text="message" role="status"></p><p x-show="error" x-text="error" role="alert" class="text-red-600"></p>
    @if($job)<div class="overflow-x-auto"><table class="w-full text-left text-lg"><thead><tr>@foreach(['Product','Pull','Pick','Prep','Stage','Out','Returned'] as $heading)<th class="p-2">{{ $heading }}</th>@endforeach</tr></thead><tbody id="warehouse-lines">@foreach($lines as $line)<tr class="border-t border-gray-200 dark:border-gray-700"><td class="py-3">{{ $line['product'] }}</td><td>{{ $line['pull'] }}</td><td>{{ $line['done']['pick'] }}</td><td>{{ $line['done']['prep'] }}</td><td>{{ $line['done']['stage'] }}</td><td>{{ $line['done']['checkout'] }}</td><td>{{ $line['done']['return'] }}</td></tr>@endforeach</tbody></table>@if(!$lines)<p>No equipment allocated.</p>@endif</div>@endif
</main>
<script>
function warehouseDisplay(jobId, initialMode) {
    return {
        jobId, mode: initialMode, code: '', pending: false, message: '', error: '',
        init() { this.refreshTimer = setInterval(() => this.refresh(), 15000); },
        destroy() { clearInterval(this.refreshTimer); },
        get canScan() { const modes = @json(collect($modes)->keyBy('value')); return !!modes[this.mode]?.allowed; },
        fullscreen() { if (document.fullscreenElement) document.exitFullscreen(); else this.$el.requestFullscreen?.(); },
        changeJob(event) { const url = new URL('{{ route('warehouse.display') }}', window.location.origin); if (event.target.value) url.pathname += '/' + event.target.value; url.searchParams.set('mode', this.mode); window.location.assign(url); },
        renderLines(lines) { const body=document.getElementById('warehouse-lines'); if(!body)return; body.replaceChildren(); for(const line of lines||[]){const row=document.createElement('tr');row.className='border-t border-gray-200 dark:border-gray-700';for(const value of [line.product,line.pull,line.done?.pick,line.done?.prep,line.done?.stage,line.done?.checkout,line.done?.return]){const cell=document.createElement('td');cell.className='p-2';cell.textContent=value??'';row.append(cell);}body.append(row);} },
        renderProgress(d) { if(d.job){this.jobId=d.job.id;const header=document.getElementById('warehouse-job-header');if(header)header.textContent=`${d.job.number} · ${d.job.name} — ${d.job.stage_label}`;} if(d.totals){const el=document.getElementById('warehouse-totals');if(el)el.textContent=`Pull ${d.totals.pull} · Picked ${d.totals.pick} · Out ${d.totals.checkout} · Returned ${d.totals.return}`;} this.renderLines(d.lines); },
        async refresh() { if (!this.jobId) return; try { const r = await fetch('{{ url('/warehouse/jobs') }}/' + this.jobId + '/progress', {headers:{Accept:'application/json'}}); if (r.ok) this.renderProgress(await r.json()); } catch (_) { this.error = 'Could not refresh progress.'; } },
        async scan() { if (this.pending || !this.code.trim()) return; this.pending = true; this.error=''; try { const r=await fetch('{{ route('warehouse.scan') }}',{method:'POST',credentials:'same-origin',headers:{'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content},body:JSON.stringify({code:this.code,mode:this.mode,job_id:this.jobId,station:'display'})}); const d=await r.json(); if(!r.ok) throw new Error(d.message||'Scan failed.'); this.message=d.result.title+': '+d.result.message; this.code=''; if(d.select_job){const u=new URL('{{ route('warehouse.display') }}/'+d.select_job.id);u.searchParams.set('mode',this.mode);location.assign(u);} else if(d.progress)this.renderProgress(d.progress); } catch(e){this.error=e.message||'Scan failed.';} finally{this.pending=false;} }
    };
}
</script>
@livewireScripts
</body>
</html>
