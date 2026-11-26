<?php

namespace App\Domain\Jobs\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class JobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('job')
            ? (bool) $this->user()?->can('jobs.jobs.edit')
            : (bool) $this->user()?->can('jobs.jobs.create');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'stage' => ['sometimes', 'nullable', 'in:inquiry,draft'],
            'workflow_status' => ['nullable', 'string', 'max:64'],
            'client_contact_id' => ['nullable', 'integer', 'exists:contacts,id'],
            'venue_location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'owner_id' => ['nullable', 'integer', 'exists:users,id'],
            'prep_starts_at' => ['nullable', 'date'],
            'starts_at' => ['nullable', 'date', 'after_or_equal:prep_starts_at'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'returns_at' => ['nullable', 'date', 'after_or_equal:ends_at'],
            'description' => ['nullable', 'string', 'max:20000'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'contacts' => ['nullable', 'array', 'max:50'],
            'contacts.*.contact_id' => ['required', 'integer', 'distinct', 'exists:contacts,id'],
            'contacts.*.role' => ['nullable', 'string', 'max:120'],
            'custom_fields' => ['nullable', 'array'],
            'override' => ['sometimes', 'boolean'],
            'override_reason' => ['nullable', 'required_if:override,true', 'string', 'min:5', 'max:500'],
        ];
    }

    public function attributes(): array
    {
        return [
            'client_contact_id' => 'client',
            'venue_location_id' => 'venue',
            'prep_starts_at' => 'prep start',
            'starts_at' => 'event start',
            'ends_at' => 'event end',
            'returns_at' => 'return date',
            'contacts.*.contact_id' => 'contact',
        ];
    }

    /** Attributes for ProductionJob::fill(). */
    public function jobAttributes(): array
    {
        return collect($this->validated())->only([
            'name', 'workflow_status', 'client_contact_id', 'venue_location_id', 'owner_id',
            'prep_starts_at', 'starts_at', 'ends_at', 'returns_at', 'description', 'color',
        ])->all();
    }
}
