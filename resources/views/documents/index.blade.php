@component('layouts.app')
<div class="mx-auto max-w-7xl space-y-5">
    <header class="flex flex-wrap items-end justify-between gap-4"><div><h1 class="text-2xl font-bold">Documents</h1><p class="text-sm text-gray-500">Generate and review document snapshots.</p></div>@can('documents.templates.view')<x-button :href="route('documents.templates.index')" color="secondary">Templates</x-button>@endcan</header>
    @can('documents.documents.create')
    <section class="rounded-lg border border-gray-200 p-4 dark:border-gray-700" x-data="documentGenerator(@js($templates), @js($entityTypes))"><h2 class="mb-3 font-semibold">Generate a document</h2>
        <form method="POST" action="{{ route('documents.store') }}" class="space-y-3">@csrf
            <label class="block text-sm">Record type<select name="entity_type" required x-model="entityType" @change="templateId='';entityId='';entities=[];lookup()" class="mt-1 block w-full rounded border p-2"><option value="">Select a type</option>@foreach($entityTypes as $type)<option value="{{ $type['value'] }}">{{ $type['label'] }}</option>@endforeach</select></label>
            <label class="block text-sm">Template<select name="template_id" required x-model="templateId" class="mt-1 block w-full rounded border p-2"><option value="">Select a template</option><template x-for="template in availableTemplates" :key="template.id"><option :value="template.id" x-text="template.name + ' · ' + template.type_label"></option></template></select></label>
            <label class="block text-sm">Find a record<input x-model="query" @keydown.enter.prevent="lookup" class="mt-1 block w-full rounded border p-2" placeholder="Search record"></label>
            <button type="button" class="rounded border px-3 py-2" :disabled="!entityType" @click="lookup">Search records</button><p x-show="lookupError" x-text="lookupError" class="text-red-600"></p>
            <label class="block text-sm">Record<select name="entity_id" x-model="entityId" required class="mt-1 block w-full rounded border p-2"><option value="">Select a record</option><template x-for="entity in entities" :key="entity.value"><option :value="entity.value" x-text="entity.label"></option></template></select></label>
            @include('partials.custom-fields', ['customFieldSchema' => $customFieldSchema, 'customFields' => []])
            <x-button type="submit" color="primary">Generate</x-button>
        </form>
    </section>
    @endcan
    @if($entityLabel)<p class="rounded border border-gray-200 p-3">Records for {{ $entityLabel }}</p>@endif
    <form method="GET" action="{{ route('documents.index') }}" class="grid gap-3 rounded-lg border border-gray-200 p-4 sm:grid-cols-2 lg:grid-cols-4">
        <label class="block text-sm">Search<input name="q" value="{{ $filters['q'] ?? '' }}" type="search" class="mt-1 block w-full rounded border p-2"></label>
        <label class="block text-sm">Document type<select name="type" class="mt-1 block w-full rounded border p-2"><option value="">All types</option>@foreach($types as $type)<option value="{{ $type['value'] }}" @selected(($filters['type'] ?? '') === $type['value'])>{{ $type['label'] }}</option>@endforeach</select></label>
        <label class="block text-sm">Record type<select name="entity_type" class="mt-1 block w-full rounded border p-2"><option value="">All records</option>@foreach($entityTypes as $type)<option value="{{ $type['value'] }}" @selected(($filters['entity_type'] ?? '') === $type['value'])>{{ $type['label'] }}</option>@endforeach</select></label>
        <label class="block text-sm">Record ID<input name="entity_id" type="number" min="1" value="{{ $filters['entity_id'] ?? '' }}" class="mt-1 block w-full rounded border p-2"></label>
        <div class="flex items-end gap-2"><x-button type="submit" color="primary">Filter</x-button><x-button :href="route('documents.index')" color="secondary">Reset</x-button></div>
    </form>
    <section class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
        @if(!$documents->count())<p>No documents found.</p>@else<div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr>@foreach(['Number / title','Type','Record','Template','Created by','Created'] as $heading)<th class="p-2">{{ $heading }}</th>@endforeach</tr></thead><tbody>@foreach($documents as $doc)<tr class="border-t border-gray-200 dark:border-gray-700"><td class="p-2"><a class="text-primary-700 underline" href="{{ route('documents.show', $doc['id']) }}">{{ $doc['number'] }} · {{ $doc['title'] }}</a></td><td>{{ $doc['type_label'] }}</td><td>@if($doc['entity_url'])<a class="text-primary-700 underline" href="{{ $doc['entity_url'] }}">{{ $doc['entity_label'] }}</a>@else{{ $doc['entity_label'] }}@endif</td><td>{{ $doc['template']['name'] ?? '—' }}</td><td>{{ $doc['created_by'] ?? '—' }}</td><td>{{ $doc['created_at'] ? \Illuminate\Support\Carbon::parse($doc['created_at'])->format('M j, Y H:i') : '—' }}</td></tr>@endforeach</tbody></table></div>
            <nav class="mt-4 flex flex-wrap gap-2" aria-label="Pagination">@foreach($documents->linkCollection() as $link)<a @if($link['url']) href="{{ $link['url'] }}" @endif aria-current="{{ $link['active'] ? 'page' : 'false' }}" class="rounded border px-3 py-1 {{ $link['active'] ? 'bg-primary-600 text-white' : '' }}">{{ strip_tags($link['label']) }}</a>@endforeach</nav>
        @endif
    </section>
</div>
<script>
function documentGenerator(templates, types) { return {templates,types,entityType:'',templateId:'',entityId:'',query:'',entities:[],lookupError:'',get availableTemplates(){return this.templates.filter(t=>t.entity_type===this.entityType);},async lookup(){if(!this.entityType)return;try{const u=new URL('{{ route('documents.entities') }}');u.searchParams.set('type',this.entityType);u.searchParams.set('q',this.query);const r=await fetch(u,{headers:{Accept:'application/json'}});if(!r.ok)throw new Error();this.entities=await r.json();this.lookupError='';}catch(_){this.lookupError='Could not load records.';}}};}
</script>
@endcomponent
