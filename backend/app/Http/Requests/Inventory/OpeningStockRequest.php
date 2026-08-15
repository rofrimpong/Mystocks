<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class OpeningStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'uuid', 'exists:products,id'],
            'branch_id' => ['required', 'uuid', 'exists:branches,id'],
            'quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,4'],
            'unit_cost' => ['nullable', 'numeric', 'min:0', 'decimal:0,4'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
