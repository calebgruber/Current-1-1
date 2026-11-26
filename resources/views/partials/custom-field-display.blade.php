@if(!empty($customFieldSchema))
    <dl class="mt-3 grid gap-2 sm:grid-cols-2">
        @foreach($customFieldSchema as $field)
            @php($key = data_get($field, 'key'))
            @php($value = data_get($customFields ?? [], $key))
            @if($value !== null && $value !== '')
                <div>
                    <dt class="text-sm text-gray-500">{{ data_get($field, 'label', $key) }}</dt>
                    <dd class="whitespace-pre-wrap break-words">
                        @if(data_get($field, 'type') === 'url' && is_string($value) && preg_match('/^https?:\/\//i', $value))
                            <a href="{{ $value }}" target="_blank" rel="noopener noreferrer" class="text-primary-700 underline dark:text-primary-300">{{ $value }}</a>
                        @elseif(data_get($field, 'type') === 'file' && is_array($value) && !empty($value['path']))
                            <a href="{{ route('custom-fields.download', ['path' => $value['path']]) }}" class="text-primary-700 underline dark:text-primary-300">{{ $value['name'] ?? 'Download file' }}</a>
                        @elseif(is_array($value))
                            {{ json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}
                        @else
                            {{ $value }}
                        @endif
                    </dd>
                </div>
            @endif
        @endforeach
    </dl>
@endif
