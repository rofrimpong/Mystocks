<?php

namespace App\Http\Requests\Sale;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'branch_id' => ['nullable', 'uuid', 'exists:branches,id'],
            'customer_id' => ['nullable', 'uuid', 'exists:customers,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'sold_at' => ['nullable', 'date'],
            'discount_amount' => ['nullable', 'numeric', 'min:0', 'decimal:0,4'],
            'tax_amount' => ['nullable', 'numeric', 'min:0', 'decimal:0,4'],
            'idempotency_key' => ['nullable', 'string', 'max:100'],
            'device_id' => ['nullable', 'string', 'max:100'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'uuid', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,4'],
            'items.*.unit_selling_price' => ['nullable', 'numeric', 'min:0', 'decimal:0,4'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0', 'decimal:0,4'],
            'payment' => ['nullable', 'array'],
            'payment.method' => ['required_with:payment', Rule::in(['cash', 'mobile_money', 'card', 'bank_transfer', 'credit', 'other'])],
            'payment.amount' => [
                Rule::requiredIf(
                    fn () => is_array($this->input('payment'))
                        && $this->input('payment.method') !== 'credit'
                ),
                'nullable',
                'numeric',
                'gt:0',
                'decimal:0,4',
            ],
            'payment.reference' => ['nullable', 'string', 'max:100'],
            'payment.provider' => ['nullable', 'string', 'max:50'],
        ];
    }
}
