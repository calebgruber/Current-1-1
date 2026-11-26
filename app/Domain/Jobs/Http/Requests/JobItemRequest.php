<?php

namespace App\Domain\Jobs\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class JobItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->isMethod('post')
            ? (bool) $this->user()?->can('jobs.items.create')
            : (bool) $this->user()?->can('jobs.items.edit');
    }

    public function rules(): array
    {
        $creating = $this->isMethod('post');

        return [
            'product_id' => [$creating ? 'required' : 'prohibited', 'integer', 'exists:products,id'],
            'required_quantity' => [$creating ? 'required' : 'sometimes', 'integer', 'min:0', 'max:100000'],
            'spare_quantity' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100000'],
            'section' => ['sometimes', 'nullable', 'string', 'max:120'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:255'],
            'unit_price' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:9999999'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'with_accessories' => ['sometimes', 'boolean'],
            'override' => ['sometimes', 'boolean'],
            'override_reason' => ['nullable', 'required_if:override,true', 'string', 'min:5', 'max:500'],
        ];
    }
}
