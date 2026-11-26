<?php

namespace App\Domain\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ProductBulkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('inventory.products.edit');
    }

    public function rules(): array
    {
        return [
            'product_ids' => ['required', 'array', 'min:1', 'max:100'],
            'product_ids.*' => ['required', 'integer', 'distinct', Rule::exists('products', 'id')->whereNull('deleted_at')],
            'changes' => ['required', 'array:category_id,is_active', 'min:1'],
            'changes.category_id' => ['sometimes', 'nullable', 'integer', 'exists:categories,id'],
            'changes.is_active' => ['sometimes', 'required', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (array_diff(array_keys($this->all()), ['product_ids', 'changes', '_token'])) {
                    $validator->errors()->add('request', 'The request contains unsupported fields.');
                }
            },
        ];
    }
}
