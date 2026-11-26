<?php

namespace App\Domain\Inventory\Http\Requests;

use App\Domain\Inventory\Enums\TrackingType;
use App\Domain\Inventory\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public const ACCESSORY_TYPES = ['required', 'optional', 'safety', 'consumable'];

    public function authorize(): bool
    {
        $product = $this->route('product');

        return $this->user()->can($product instanceof Product ? 'inventory.products.edit' : 'inventory.products.create')
            && (! $this->hasAny(['accessories', 'alternatives']) || $this->user()->can('inventory.products.view'));
    }

    protected function prepareForValidation(): void
    {
        foreach (['sku', 'barcode', 'model'] as $key) {
            if (is_string($this->input($key))) {
                $this->merge([$key => trim($this->input($key)) ?: null]);
            }
        }
    }

    public function rules(): array
    {
        $product = $this->route('product');
        $id = $product instanceof Product ? $product->id : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:64', Rule::unique('products', 'sku')->ignore($id)],
            'barcode' => ['nullable', 'string', 'max:128', 'regex:/^[\x20-\x7E]+$/', Rule::unique('products', 'barcode')->ignore($id)],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'manufacturer_id' => ['nullable', 'integer', 'exists:manufacturers,id'],
            'manufacturer_name' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:20000'],
            'tracking' => ['required', Rule::enum(TrackingType::class), ...($product instanceof Product ? [Rule::in([$product->tracking->value])] : [])],
            'replacement_cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'rental_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'weight_kg' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'default_location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'is_active' => ['boolean'],
            'tags' => ['nullable', 'array', 'max:30'],
            'tags.*' => ['string', 'max:50'],
            'metadata' => ['nullable', 'array', 'max:50'],
            'metadata.*.key' => ['nullable', 'string', 'max:64'],
            'metadata.*.value' => ['nullable', 'string', 'max:1000'],
            'accessories' => ['nullable', 'array', 'max:100'],
            'accessories.*.accessory_product_id' => [
                'required', 'integer', 'distinct',
                Rule::exists('products', 'id')->whereNull('deleted_at'),
                ...($id ? [Rule::notIn([$id])] : []),
            ],
            'accessories.*.quantity' => ['required', 'numeric', 'min:0.01', 'max:10000'],
            'accessories.*.type' => ['required', Rule::in(self::ACCESSORY_TYPES)],
            'accessories.*.auto_add' => ['boolean'],
            'accessories.*.notes' => ['nullable', 'string', 'max:255'],
            'alternatives' => ['nullable', 'array', 'max:100'],
            'alternatives.*.product_id' => [
                'required', 'integer', 'distinct',
                Rule::exists('products', 'id')->whereNull('deleted_at'),
                ...($id ? [Rule::notIn([$id])] : []),
            ],
            'stock' => ['nullable', 'array', 'max:100'],
            'stock.*.location_id' => ['nullable', 'integer', 'distinct', 'exists:locations,id'],
            'stock.*.quantity' => ['required', 'integer', 'min:0', 'max:10000000'],
            'stock.*.quantity_maintenance' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'stock.*.quantity_damaged' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'stock.*.quantity_lost' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'custom_fields' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'accessories.*.accessory_product_id.not_in' => 'A product cannot be its own accessory.',
            'accessories.*.accessory_product_id.distinct' => 'Each accessory can only be listed once.',
            'tracking.in' => 'Tracking cannot be changed after a product is created.',
            'alternatives.*.product_id.not_in' => 'A product cannot be its own alternative.',
            'alternatives.*.product_id.distinct' => 'Each alternative can only be listed once.',
            'barcode.regex' => 'Barcodes may only contain printable ASCII characters.',
        ];
    }

    public function attributes(): array
    {
        return [
            'accessories.*.accessory_product_id' => 'accessory',
            'accessories.*.quantity' => 'quantity',
            'alternatives.*.product_id' => 'alternative product',
            'stock.*.location_id' => 'location',
            'stock.*.quantity' => 'quantity',
        ];
    }
}
