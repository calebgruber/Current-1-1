@component('layouts.app')
<div class="mx-auto max-w-5xl space-y-5">
    <header class="border-l-4 border-indigo-500 pl-4">
        <h1 class="text-2xl font-bold">Organization settings</h1>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Personalize names, dates, currency, and shop behavior for this deployment.</p>
    </header>
    @if(session('success'))<x-alert color="green"><x-icon name="check-circle" class="mr-1 inline size-4" />{{ session('success') }}</x-alert>@endif
    @if($errors->any())<x-alert color="red">{{ $errors->first() }}</x-alert>@endif
    <nav aria-label="Settings sections" class="flex flex-wrap gap-2 border-b border-gray-200 pb-2 dark:border-dark-600">
        <a href="#organization" class="module-tab bg-indigo-50 text-indigo-800 dark:bg-indigo-950 dark:text-indigo-200"><x-icon name="building-office-2" class="size-4" /> Organization</a>
        <a href="#workflow" class="module-tab bg-amber-50 text-amber-800 dark:bg-amber-950 dark:text-amber-200"><x-icon name="arrow-path" class="size-4" /> Workflow</a>
        <a href="#labels" class="module-tab bg-sky-50 text-sky-800 dark:bg-sky-950 dark:text-sky-200"><x-icon name="tag" class="size-4" /> Module names</a>
        @can('settings.custom_fields.view')<x-button color="secondary" :href="route('settings.custom-fields.index')"><x-icon name="list-bullet" class="size-4" /> Custom fields</x-button>@endcan
    </nav>
    <form method="POST" action="{{ route('settings.general.update') }}" class="space-y-5">
        @csrf @method('PUT')
        <section id="organization" class="scroll-mt-24">
            <x-card bordered shadowless header="Organization">
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="block text-sm font-medium">Organization name <x-input name="organization_name" value="{{ old('organization_name', $settings['general.organization_name']) }}" required maxlength="255" /></label>
                    <label class="block text-sm font-medium">Currency (ISO code) <x-input name="currency" value="{{ old('currency', $settings['general.currency']) }}" required maxlength="3" pattern="[A-Z]{3}" /></label>
                    <label class="block text-sm font-medium">Timezone <x-input name="timezone" value="{{ old('timezone', $settings['general.timezone']) }}" required list="timezones" /><datalist id="timezones">@foreach(timezone_identifiers_list() as $timezone)<option value="{{ $timezone }}">@endforeach</datalist></label>
                    <label class="block text-sm font-medium">Date format
                        <select name="date_format" class="mt-1 block w-full rounded border-gray-300 dark:border-dark-500 dark:bg-dark-700">@foreach(['M j, Y', 'j M Y', 'Y-m-d', 'm/d/Y', 'd/m/Y'] as $format)<option value="{{ $format }}" @selected(old('date_format', $settings['general.date_format']) === $format)>{{ now()->format($format) }} ({{ $format }})</option>@endforeach</select>
                    </label>
                    <label class="block text-sm font-medium">Default job duration (days) <x-input name="default_job_duration_days" type="number" min="1" max="365" value="{{ old('default_job_duration_days', $settings['general.default_job_duration_days']) }}" required /></label>
                    <label class="block text-sm font-medium">Tax rate (%) <x-input name="tax_rate" type="number" min="0" max="100" step="0.01" value="{{ old('tax_rate', $settings['general.tax_rate']) }}" required /></label>
                    <label class="block text-sm font-medium">Tax label <x-input name="tax_label" value="{{ old('tax_label', $settings['general.tax_label']) }}" required maxlength="40" /></label>
                </div>
            </x-card>
        </section>
        <section id="workflow" class="scroll-mt-24">
            <x-card bordered shadowless header="Workflow defaults">
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach([
                        'block_overbooking' => ['reservations.block_overbooking', 'Prevent overbooking by default'],
                        'count_tentative' => ['reservations.count_tentative', 'Count tentative holds against availability'],
                        'allow_over_pull' => ['warehouse.allow_over_pull', 'Allow warehouse pulls above requested quantity'],
                        'sound_enabled' => ['warehouse.sound_enabled', 'Play sounds for successful scans'],
                    ] as $name => [$key, $label])
                        <label class="flex items-start gap-3 rounded-none border border-gray-200 bg-slate-50 p-3 dark:border-dark-600 dark:bg-dark-700">
                            <input type="hidden" name="{{ $name }}" value="0">
                            <input type="checkbox" name="{{ $name }}" value="1" @checked(old($name, $settings[$key])) class="mt-1 rounded border-gray-300">
                            <span><span class="block font-medium">{{ $label }}</span><span class="text-xs text-gray-500">Applied across this deployment.</span></span>
                        </label>
                    @endforeach
                </div>
            </x-card>
        </section>
        <section id="labels" class="scroll-mt-24">
            <x-card bordered shadowless header="Module names">
                <p class="mb-4 text-sm text-gray-600 dark:text-gray-300">Change the names shown in the main navigation to match your shop’s terminology.</p>
                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach(['inventory' => 'Inventory', 'jobs' => 'Jobs', 'maintenance' => 'Maintenance', 'warehouse' => 'Warehouse', 'reservations' => 'Reservations', 'contacts' => 'Contacts', 'documents' => 'Documents', 'reports' => 'Reports'] as $name => $default)
                        <label class="block text-sm font-medium">{{ $default }} tab <x-input name="label_{{ $name }}" value="{{ old('label_'.$name, $settings['ui.label_'.$name] ?? $default) }}" required maxlength="40" /></label>
                    @endforeach
                </div>
            </x-card>
        </section>
        <div class="sticky bottom-0 flex justify-end border-t border-gray-200 bg-white/95 py-3 backdrop-blur dark:border-dark-600 dark:bg-dark-800/95">
            <x-button type="submit" color="primary"><x-icon name="check" class="size-4" /> Save settings</x-button>
        </div>
    </form>
</div>
@endcomponent
