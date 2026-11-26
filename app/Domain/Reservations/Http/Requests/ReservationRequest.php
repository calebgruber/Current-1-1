<?php

namespace App\Domain\Reservations\Http\Requests;

use App\Domain\Reservations\Enums\ReservationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('reservation')
            ? (bool) $this->user()?->can('reservations.reservations.edit')
            : (bool) $this->user()?->can('reservations.reservations.create');
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')],
            'job_id' => ['nullable', 'integer', Rule::exists('production_jobs', 'id')->whereNull('deleted_at')],
            'name' => ['nullable', 'required_without:job_id', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'spare_quantity' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'status' => ['required', Rule::enum(ReservationStatus::class)],
            'notes' => ['nullable', 'string', 'max:5000'],
            'custom_fields' => ['nullable', 'array'],
            'override' => ['sometimes', 'boolean'],
            'override_reason' => ['nullable', 'required_if:override,true', 'string', 'min:5', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required_without' => 'Give the hold a name or link it to a job.',
            'ends_at.after' => 'The hold must end after it starts.',
            'override_reason.required_if' => 'Explain why this overbooking is acceptable.',
        ];
    }

    public function attributes(): array
    {
        return ['product_id' => 'product', 'job_id' => 'job', 'starts_at' => 'start', 'ends_at' => 'end'];
    }
}
