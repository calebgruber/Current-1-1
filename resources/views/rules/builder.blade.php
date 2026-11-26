@component('layouts.app')
<div class="mx-auto max-w-5xl space-y-4" x-data="ruleBuilder(@js($rule), @js($presets))">
    <header class="flex flex-wrap items-end justify-between gap-3"><h1 class="text-2xl font-bold">{{ $rule['id'] ? 'Edit rule' : 'New rule' }}</h1><x-button :href="route('rules.index')" color="secondary">Rules</x-button></header>
    <form method="POST" action="{{ $rule['id'] ? route('rules.update',$rule['id']) : route('rules.store') }}" class="space-y-4 rounded-lg border border-gray-200 p-4 dark:border-gray-700" @submit.prevent="submit">@csrf @if($rule['id'])@method('PUT')@endif
        <label class="block text-sm">Start from preset<select class="mt-1 block w-full rounded border p-2" @change="preset($event.target.value)"><option value="">Choose a preset</option>@foreach($presets as $preset)<option value="{{ $preset['key'] }}">{{ $preset['name'] }}</option>@endforeach</select></label>
        <label class="block text-sm">Name<input name="name" x-model="form.name" required maxlength="255" class="mt-1 block w-full rounded border p-2"></label>
        <label class="block text-sm">Description<textarea name="description" x-model="form.description" class="mt-1 block w-full rounded border p-2"></textarea></label>
        <label class="block text-sm">Severity<select name="severity" x-model="form.severity" class="mt-1 block w-full rounded border p-2">@foreach($severities as $severity)<option value="{{ $severity }}">{{ ucfirst($severity) }}</option>@endforeach</select></label>
        <input type="hidden" name="is_active" value="0"><label class="block"><input name="is_active" type="checkbox" value="1" x-model="form.is_active"> Active</label>
        <label class="block text-sm">Rule definition JSON<textarea x-model="definition" rows="18" class="mt-1 block w-full rounded border p-2 font-mono text-sm"></textarea></label>
        <details class="text-sm"><summary class="cursor-pointer">Rule builder reference</summary><div class="grid gap-3 pt-3 sm:grid-cols-2"><div><strong>Checks</strong><p>{{ implode(', ', $checks) }}</p><strong>Selector matches</strong><p>{{ implode(', ', $matches) }}</p></div><div><strong>Categories</strong><p>{{ collect($categories)->pluck('label')->join(', ') ?: 'None' }}</p><strong>Tags</strong><p>{{ collect($tags)->pluck('label')->join(', ') ?: 'None' }}</p><strong>Custom fields</strong><p>{{ collect($customFields)->pluck('label')->join(', ') ?: 'None' }}</p></div></div></details>
        <p x-show="error" x-text="error" class="text-red-600" role="alert"></p>
        @if($errors->any())<div class="text-red-600" role="alert">@foreach($errors->all() as $message)<p>{{ $message }}</p>@endforeach</div>@endif
        <x-button type="submit" color="primary">Save rule</x-button>
    </form>
</div>
<script>
function ruleBuilder(rule,presets){return{form:JSON.parse(JSON.stringify(rule)),presets,definition:JSON.stringify(rule.definition||{},null,2),error:'',preset(key){const p=this.presets.find(x=>x.key===key);if(p){this.form.name=p.name;this.form.description=p.description;this.form.severity=p.severity;this.definition=JSON.stringify(p.definition,null,2);}},append(form,key,v){if(v===null||v===undefined){form.append(key,'');return;}if(Array.isArray(v)){v.forEach((x,i)=>this.append(form,`${key}[${i}]`,x));return;}if(typeof v==='object'){Object.entries(v).forEach(([k,x])=>this.append(form,key?`${key}[${k}]`:k,x));return;}form.append(key,String(v));},submit(event){let value;try{value=JSON.parse(this.definition);if(!value||Array.isArray(value)||typeof value!=='object')throw new Error('Definition must be an object.');}catch(e){this.error=e.message||'Invalid definition JSON.';return;}this.error='';const f=event.currentTarget;f.querySelectorAll('[data-generated]').forEach(x=>x.remove());const d=new FormData();this.append(d,'definition',value);for(const[k,v]of d.entries()){const i=document.createElement('input');i.type='hidden';i.name=k;i.value=v;i.dataset.generated='1';f.append(i);}f.submit();}}}
</script>
@endcomponent
