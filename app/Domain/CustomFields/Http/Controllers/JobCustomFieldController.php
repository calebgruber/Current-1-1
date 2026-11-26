<?php

namespace App\Domain\CustomFields\Http\Controllers;

use App\Domain\CustomFields\Enums\CustomFieldType;
use App\Domain\CustomFields\Models\CustomFieldDefinition;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class JobCustomFieldController extends Controller
{
    private const TYPES = ['text', 'long_text', 'number', 'currency', 'date', 'checkbox', 'dropdown', 'multi_select', 'url'];

    public function index(): Response
    {
        Gate::authorize('settings.custom_fields.view');

        return Inertia::render('CustomFields/Index', [
            'fields' => CustomFieldDefinition::query()->where('entity_type', 'job')
                ->orderBy('sort_order')->orderBy('id')
                ->get(['id', 'key', 'label', 'type', 'is_required', 'options', 'help_text']),
            'types' => array_values(array_filter(CustomFieldType::options(), fn ($type) => in_array($type['value'], self::TYPES, true))),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('settings.custom_fields.create');

        $data = $request->validate([
            'key' => ['required', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_]*$/', Rule::unique('custom_field_definitions', 'key')->where('entity_type', 'job')],
            'label' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(self::TYPES)],
            'help_text' => ['nullable', 'string', 'max:255'],
            'is_required' => ['required', 'boolean'],
            'options' => ['exclude_unless:type,dropdown,multi_select', 'required', 'array', 'min:1', 'max:100'],
            'options.*' => ['required', 'string', 'max:255', 'distinct'],
        ]);

        CustomFieldDefinition::create([
            'entity_type' => 'job',
            'key' => $data['key'],
            'label' => $data['label'],
            'type' => $data['type'],
            'help_text' => $data['help_text'] ?? null,
            'is_required' => $data['type'] === 'checkbox' ? false : $data['is_required'],
            'options' => $data['options'] ?? null,
        ]);

        return redirect()->route('settings.custom-fields.index')->with('success', 'Job field created.');
    }

    public function update(Request $request, CustomFieldDefinition $definition): RedirectResponse
    {
        Gate::authorize('settings.custom_fields.edit');
        abort_unless($definition->entity_type === 'job', 404);

        $data = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'help_text' => ['nullable', 'string', 'max:255'],
        ]);
        $definition->update([
            'label' => $data['label'],
            'help_text' => $data['help_text'] ?? null,
        ]);

        return redirect()->route('settings.custom-fields.index')->with('success', 'Job field updated.');
    }
}
