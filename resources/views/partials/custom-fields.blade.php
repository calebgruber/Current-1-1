@if(!empty($customFieldSchema))
    <fieldset class="space-y-4">
        <legend class="text-lg font-semibold">Additional fields</legend>
        <div class="grid gap-4 sm:grid-cols-2">
            @foreach($customFieldSchema as $field)
                @php
                    $key = data_get($field, 'key');
                    $type = data_get($field, 'type', 'text');
                    $value = old("custom_fields.$key", data_get($customFields ?? [], $key));
                    $options = data_get($field, 'options', []);
                @endphp
                <label class="block text-sm {{ in_array($type, ['long_text', 'multi_select'], true) ? 'sm:col-span-2' : '' }}">
                    {{ data_get($field, 'label', $key) }} @if(data_get($field, 'required'))<span aria-hidden="true">*</span>@endif
                    @if($type === 'long_text')
                        <textarea name="custom_fields[{{ $key }}]" rows="3" class="mt-1 block w-full rounded border-gray-300" @required(data_get($field, 'required'))>{{ is_scalar($value) ? $value : '' }}</textarea>
                    @elseif($type === 'dropdown')
                        <select name="custom_fields[{{ $key }}]" class="mt-1 block w-full rounded border-gray-300" @required(data_get($field, 'required'))><option value="">—</option>@foreach($options as $option)@php($optionValue = is_array($option) ? ($option['value'] ?? '') : $option)<option value="{{ $optionValue }}" @selected($value == $optionValue)>{{ is_array($option) ? ($option['label'] ?? $optionValue) : $option }}</option>@endforeach</select>
                    @elseif($type === 'multi_select')
                        <div class="mt-1 flex flex-wrap gap-3">@foreach($options as $option)@php($optionValue = is_array($option) ? ($option['value'] ?? '') : $option)<label class="flex items-center gap-1"><input type="checkbox" name="custom_fields[{{ $key }}][]" value="{{ $optionValue }}" @checked(is_array($value) && in_array($optionValue, $value, true))>{{ is_array($option) ? ($option['label'] ?? $optionValue) : $option }}</label>@endforeach</div>
                    @elseif($type === 'checkbox')
                        <input type="hidden" name="custom_fields[{{ $key }}]" value="0"><input type="checkbox" name="custom_fields[{{ $key }}]" value="1" class="ml-2" @checked((bool)$value)>
                    @elseif($type === 'relation')
                        @php($entities = data_get($field, 'validation.allowed') ?: [data_get($field, 'validation.entity', 'product')])
                        @php($relationType = data_get($value, 'type', $entities[0] ?? 'product'))
                        <div class="mt-1 grid grid-cols-2 gap-2"><select name="custom_fields[{{ $key }}][type]" class="rounded border-gray-300" data-relation-type>@foreach($entities as $entity)<option value="{{ $entity }}" @selected($relationType === $entity)>{{ ucfirst($entity) }}</option>@endforeach</select>
                            <div class="relative" data-custom-field-lookup data-endpoint="{{ route('custom-fields.lookup', ['type' => $relationType]) }}">
                                <input type="text" role="combobox" aria-expanded="false" autocomplete="off" class="block w-full rounded border-gray-300" value="{{ data_get($value, 'id') ? '#'.data_get($value, 'id') : '' }}" placeholder="Search record">
                                <input type="hidden" name="custom_fields[{{ $key }}][id]" value="{{ data_get($value, 'id') }}">
                                <ul role="listbox" class="absolute z-40 mt-1 hidden max-h-60 w-full overflow-auto rounded border bg-white shadow"></ul>
                            </div>
                        </div>
                    @elseif($type === 'file')
                        <div class="mt-1" x-data="customFileField(@js($value))">
                            <template x-if="value?.path"><div class="flex items-center gap-2 rounded border p-2 text-sm"><a class="flex-1 underline" :href="'{{ route('custom-fields.download') }}?path=' + encodeURIComponent(value.path)" x-text="value.name"></a><button type="button" class="text-red-700 underline" @click="value = null">Remove</button></div></template>
                            <template x-if="!value?.path"><input type="file" class="block w-full text-sm" @change="upload($event)" :disabled="uploading"></template>
                            <template x-if="value?.path"><span><input type="hidden" name="custom_fields[{{ $key }}][path]" :value="value.path"><input type="hidden" name="custom_fields[{{ $key }}][name]" :value="value.name"><input type="hidden" name="custom_fields[{{ $key }}][size]" :value="value.size"></span></template>
                            <span class="text-xs text-gray-500" x-show="uploading">Uploading…</span><span class="text-xs text-red-600" x-text="error"></span>
                        </div>
                    @elseif(in_array($type, ['user', 'asset'], true))
                        <div class="relative mt-1" data-custom-field-lookup data-endpoint="{{ route('custom-fields.lookup', ['type' => $type]) }}">
                            <input type="text" role="combobox" aria-expanded="false" autocomplete="off" class="block w-full rounded border-gray-300" value="{{ old("custom_fields.{$key}__label", $value ? '#'.$value : '') }}" placeholder="Search {{ $type }}">
                            <input type="hidden" name="custom_fields[{{ $key }}]" value="{{ $value }}">
                            <ul role="listbox" class="absolute z-40 mt-1 hidden max-h-60 w-full overflow-auto rounded border bg-white shadow"></ul>
                        </div>
                    @elseif(in_array($type, ['text', 'number', 'currency', 'date', 'datetime', 'url'], true))
                        @php($inputType = match($type) { 'number', 'currency' => 'number', 'date' => 'date', 'datetime' => 'datetime-local', 'url' => 'url', default => 'text' })
                        <input name="custom_fields[{{ $key }}]" type="{{ $inputType }}" value="{{ is_scalar($value) ? $value : '' }}" class="mt-1 block w-full rounded border-gray-300" @required(data_get($field, 'required')) @if(in_array($type, ['number','currency'], true)) step="any" @endif>
                    @else
                        <input name="custom_fields[{{ $key }}]" value="{{ is_scalar($value) ? $value : '' }}" class="mt-1 block w-full rounded border-gray-300">
                    @endif
                    <script>
                    function customFileField(initialValue) {
                        return {
                            value: initialValue || null,
                            uploading: false,
                            error: '',
                            async upload(event) {
                                const file = event.target.files?.[0];
                                if (!file) return;
                                this.uploading = true;
                                this.error = '';
                                const body = new FormData();
                                body.append('file', file);
                                try {
                                    const response = await fetch(@js(route('custom-fields.upload')), {
                                        method: 'POST',
                                        credentials: 'same-origin',
                                        headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content},
                                        body
                                    });
                                    const data = await response.json();
                                    if (!response.ok) throw new Error(data.message || 'Upload failed.');
                                    this.value = data;
                                } catch (error) {
                                    this.error = error.message || 'Upload failed.';
                                } finally {
                                    this.uploading = false;
                                    event.target.value = '';
                                }
                            }
                        };
                    }
                    </script>
                    @if(data_get($field, 'help_text'))<span class="mt-1 block text-xs text-gray-500">{{ data_get($field, 'help_text') }}</span>@endif
                    @error("custom_fields.$key")<span class="text-sm text-red-600">{{ $message }}</span>@enderror
                </label>
            @endforeach
        </div>
    </fieldset>
    <script>
    (() => {
        document.querySelectorAll('[data-custom-field-lookup]').forEach((lookup) => {
            const input = lookup.querySelector('[role=combobox]');
            const hidden = lookup.querySelector('input[type=hidden]');
            const list = lookup.querySelector('[role=listbox]');
            const type = lookup.closest('label')?.querySelector('[data-relation-type]');
            let timer;
            const close = () => { list.classList.add('hidden'); input.setAttribute('aria-expanded', 'false'); };
            const search = () => {
                clearTimeout(timer);
                timer = setTimeout(async () => {
                    const url = new URL(lookup.dataset.endpoint, window.location.origin);
                    url.searchParams.set('q', input.value.replace(/^#/, ''));
                    const response = await fetch(url, {headers: {'Accept': 'application/json'}});
                    const options = response.ok ? await response.json() : [];
                    list.replaceChildren();
                    options.forEach((option) => {
                        const row = document.createElement('li');
                        const button = document.createElement('button');
                        button.type = 'button'; button.className = 'block w-full px-3 py-2 text-left hover:bg-gray-100'; button.setAttribute('role', 'option');
                        button.textContent = option.label + (option.description ? ' · ' + option.description : '');
                        button.addEventListener('mousedown', (event) => event.preventDefault());
                        button.addEventListener('click', () => { hidden.value = option.value; input.value = option.label; close(); });
                        row.append(button); list.append(row);
                    });
                    if (options.length) { list.classList.remove('hidden'); input.setAttribute('aria-expanded', 'true'); } else close();
                }, 200);
            };
            input.addEventListener('focus', search);
            input.addEventListener('input', () => { hidden.value = ''; search(); });
            input.addEventListener('keydown', (event) => { if (event.key === 'Escape') close(); if (event.key === 'ArrowDown') list.querySelector('button')?.focus(); });
            lookup.addEventListener('focusout', (event) => { if (!lookup.contains(event.relatedTarget)) setTimeout(close, 100); });
            if (type) type.addEventListener('change', () => { lookup.dataset.endpoint = new URL(@js(route('custom-fields.lookup')), window.location.origin) + '?type=' + encodeURIComponent(type.value); hidden.value = ''; input.value = ''; });
        });
    })();
    </script>
@endif
