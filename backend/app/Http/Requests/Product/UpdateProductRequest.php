<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:200'],
            'sku' => ['nullable', 'string', 'max:50'],
            'barcode' => ['nullable', 'string', 'max:50'],
            'category_id' => ['nullable', 'uuid', 'exists:categories,id'],
            'brand' => ['nullable', 'string', 'max:100'],
            'unit' => ['sometimes', 'string', 'max:30', Rule::in([
                'piece', 'box', 'carton', 'kilogram', 'gram', 'litre', 'metre', 'service', 'pack', 'dozen',
            ])],
            'description' => ['nullable', 'string', 'max:2000'],
            'image_path' => ['nullable', 'string', 'max:500'],
            'buying_price' => ['sometimes', 'numeric', 'min:0', 'decimal:0,4'],
            'selling_price' => ['sometimes', 'numeric', 'min:0', 'decimal:0,4'],
            'minimum_stock_level' => ['nullable', 'numeric', 'min:0', 'decimal:0,4'],
            'track_inventory' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'is_service' => ['sometimes', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
