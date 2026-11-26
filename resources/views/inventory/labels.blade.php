@component('layouts.app')
<div class="mx-auto max-w-7xl space-y-5">
    <div class="flex flex-wrap items-end justify-between gap-4"><div><h1 class="text-2xl font-bold">{{ $title }}</h1><p class="text-sm text-gray-500">{{ count($labels) }} label(s), {{ $copies }} copies each.</p></div><div class="flex gap-2"><x-button :href="$backUrl" color="secondary">Back</x-button><x-button type="button" color="primary" onclick="window.print()">Print</x-button></div></div>
    @if(count($labels) === 0)<x-card bordered shadowless><p class="text-gray-500">No records were selected for printing. Return to the list and choose a record’s Print label action.</p></x-card>@endif
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 print:grid-cols-3">
        @foreach($labels as $label)
        <article class="break-inside-avoid rounded border border-gray-300 p-4 text-center">
            <h2 class="font-semibold">{{ $label['title'] }}</h2><p class="text-sm">{{ $label['subtitle'] }}</p><p class="font-mono text-sm">{{ $label['code'] }}</p>
            <div class="mt-3 flex items-center justify-center gap-3">@if($label['barcode_svg'])<div aria-label="Barcode">{!! $label['barcode_svg'] !!}</div>@endif @if($label['qr_svg'])<div aria-label="QR code">{!! $label['qr_svg'] !!}</div>@endif</div>
        </article>
        @endforeach
    </div>
</div>
<style media="print">body { background: white !important; } aside, header, button, nav { display: none !important; }</style>
@endcomponent
