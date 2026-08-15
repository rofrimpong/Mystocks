<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $businessId = $this->attributes->get('current_business')?->id;

        return [
            'name' => ['required', 'string', 'max:200'],
            'sku' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('products', 'sku')->where(fn ($q) => $q->where('business_id', $businessId)->whereNull('deleted_at')),
            ],
            'barcode' => ['nullable', 'string', 'max:50'],
            'category_id' => ['nullable', 'uuid', 'exists:categories,id'],
            'brand' => ['nullable', 'string', 'max:100'],
            'unit' => ['required', 'string', 'max:30', Rule::in([
                'piece', 'box', 'carton', 'kilogram', 'gram', 'litre', 'metre', 'service', 'pack', 'dozen',
            ])],
            'description' => ['nullable', 'string', 'max:2000'],
            'image_path' => ['nullable', 'string', 'max:500'],
            'buying_price' => ['required', 'numeric', 'min:0', 'decimal:0,4'],
            'selling_price' => ['required', 'numeric', 'min:0', 'decimal:0,4'],
            'minimum_stock_level' => ['nullable', 'numeric', 'min:0', 'decimal:0,4'],
            'track_inventory' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'is_service' => ['sometimes', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'sku.unique' => 'This SKU is already used by another product in your business.',
            'unit.in' => 'Invalid unit. Allowed: piece, box, carton, kilogram, gram, litre, metre, service, pack, dozen.',
        ];
    }
}
