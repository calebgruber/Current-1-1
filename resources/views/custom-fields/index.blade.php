@component('layouts.app')
<div class="mx-auto max-w-5xl space-y-5">
    <div class="flex flex-wrap items-end justify-between gap-3"><div><h1 class="text-2xl font-bold">Job custom fields</h1><p class="text-sm text-gray-500">Define additional information to capture on jobs.</p></div>@can('jobs.jobs.view')<a class="text-primary-700 underline" href="{{ route('jobs.index') }}">All jobs</a>@endcan</div>
    @if(session('success'))<x-alert color="green">{{ session('success') }}</x-alert>@endif
    <x-card bordered shadowless>
        <h2 class="mb-2 text-lg font-semibold">Existing job fields</h2>
        <p class="mb-3 text-sm text-gray-500">Only labels and help text can be edited. Keys, types and options cannot be changed here; existing values are preserved. Other entity types are not managed on this page.</p>
        @forelse($fields as $field)
            <div class="border-t py-3">
                <div class="flex flex-wrap items-start justify-between gap-2"><div><span class="font-medium">{{ $field->label }}</span> <span class="text-sm text-gray-500">· {{ $field->key }} · {{ $field->type->value }}{{ $field->is_required ? ' · Required' : '' }}</span>@if($field->help_text)<p class="text-sm text-gray-500">{{ $field->help_text }}</p>@endif</div>
                    @can('settings.custom_fields.edit')<button type="button" class="text-primary-700 underline" onclick="document.getElementById('edit-field-{{ $field->id }}').classList.toggle('hidden')">Edit {{ $field->label }}</button>@endcan
                </div>
                @can('settings.custom_fields.edit')
                    <form id="edit-field-{{ $field->id }}" class="hidden mt-3 space-y-3" method="POST" action="{{ route('settings.custom-fields.update', $field) }}">@csrf @method('PUT')
                        <label class="block text-sm">Label <x-input name="label" value="{{ $field->label }}" maxlength="255" required /></label>
                        <label class="block text-sm">Help text <x-input name="help_text" value="{{ $field->help_text }}" maxlength="255" /></label>
                        <x-button type="submit" color="primary">Save</x-button>
                    </form>
                @endcan
            </div>
        @empty<p class="text-gray-500">No custom fields configured for jobs.</p>@endforelse
    </x-card>
    @can('settings.custom_fields.create')
        <x-card bordered shadowless>
            <h2 class="mb-3 text-lg font-semibold">Add job field</h2>
            <form method="POST" action="{{ route('settings.custom-fields.store') }}" class="max-w-3xl space-y-4" id="custom-field-form">@csrf
                @if($errors->any())<div class="text-sm text-red-600" role="alert">{{ $errors->first() }}</div>@endif
                <label class="block text-sm">Label <x-input name="label" value="{{ old('label') }}" maxlength="255" required /></label>
                <label class="block text-sm">Key <x-input name="key" value="{{ old('key') }}" maxlength="64" pattern="[a-z][a-z0-9_]*" required /><span class="text-sm text-gray-500">Start with a lowercase letter; use lowercase letters, numbers and underscores only. Keys cannot be changed later.</span></label>
                <label class="block text-sm">Type <select id="field-type" name="type" class="mt-1 block w-full rounded border-gray-300">@foreach($types as $type)<option value="{{ $type['value'] }}" @selected(old('type', 'text') === $type['value'])>{{ $type['label'] }}</option>@endforeach</select></label>
                <label id="field-options" class="hidden block text-sm">Options, one per line <textarea id="field-options-input" name="options_text" rows="4" class="mt-1 block w-full rounded border-gray-300">{{ old('options_text') }}</textarea><span class="text-sm text-gray-500">Enter one unique option on each line.</span></label>
                <label class="block text-sm">Help text <x-input name="help_text" value="{{ old('help_text') }}" maxlength="255" /></label>
                <label id="field-required" class="flex items-center gap-2 text-sm"><input type="hidden" name="is_required" value="0"><input type="checkbox" name="is_required" value="1" @checked(old('is_required'))> Required</label>
                <p class="text-sm text-gray-500">A required field must be filled in when creating or editing a job, including existing jobs.</p>
                <x-button type="submit" color="primary">Add field</x-button>
            </form>
        </x-card>
    @endcan
</div>
<script>
(() => {
    const type = document.getElementById('field-type');
    if (!type) return;
    const options = document.getElementById('field-options');
    const optionsInput = document.getElementById('field-options-input');
    const required = document.getElementById('field-required');
    const update = () => {
        const selectable = ['dropdown', 'multi_select'].includes(type.value);
        options.classList.toggle('hidden', !selectable);
        optionsInput.required = selectable;
        required.classList.toggle('hidden', type.value === 'checkbox');
    };
    type.addEventListener('change', update); update();
    document.getElementById('custom-field-form').addEventListener('submit', () => {
        const input = document.createElement('input');
        input.type = 'hidden'; input.name = 'options[]';
        optionsInput.value.split('\n').map(option => option.trim()).filter(Boolean).forEach(option => {
            const item = input.cloneNode(); item.value = option; document.getElementById('custom-field-form').append(item);
        });
        optionsInput.disabled = true;
    });
})();
</script>
@endcomponent
