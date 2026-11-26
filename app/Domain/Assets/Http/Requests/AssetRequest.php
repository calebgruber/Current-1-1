<?php

namespace App\Domain\Assets\Http\Requests;

use App\Domain\Assets\Models\Asset;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssetRequest extends FormRequest
{
    public const CONDITIONS = ['new', 'good', 'fair', 'poor'];

    public function authorize(): bool
    {
        return $this->user()->can($this->route('asset') instanceof Asset ? 'assets.assets.edit' : 'assets.assets.create');
    }

    protected function prepareForValidation(): void
    {
        foreach (['asset_number', 'serial_number', 'barcode', 'prefix'] as $key) {
            if (is_string($this->input($key))) {
                $this->merge([$key => trim($this->input($key)) ?: null]);
            }
        }
    }

    public function rules(): array
    {
        $asset = $this->route('asset');
        $id = $asset instanceof Asset ? $asset->id : null;
        $creating = $id === null;

        return [
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')->whereNull('deleted_at')->where('tracking', 'serialized')],
            'asset_number' => [$creating ? 'nullable' : 'required', 'string', 'max:64', 'regex:/^[\x20-\x7E]+$/', Rule::unique('assets', 'asset_number')->ignore($id)],
            'serial_number' => ['nullable', 'string', 'max:128'],
            'barcode' => ['nullable', 'string', 'max:128', 'regex:/^[\x20-\x7E]+$/', Rule::unique('assets', 'barcode')->ignore($id)],
            'condition' => ['required', Rule::in(self::CONDITIONS)],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'purchased_at' => ['nullable', 'date'],
            'purchase_cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'warranty_expires_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'custom_fields' => ['nullable', 'array'],
            // bulk creation (create only)
            'count' => [$creating ? 'nullable' : 'prohibited', 'integer', 'min:1', 'max:200'],
            'prefix' => ['nullable', 'string', 'max:32', 'regex:/^[A-Za-z0-9\-_.\/]+$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.exists' => 'Choose an active serialized product.',
            'count.max' => 'At most 200 assets can be created at once.',
        ];
    }
}
