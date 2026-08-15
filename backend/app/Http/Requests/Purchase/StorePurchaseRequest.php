<?php

namespace App\Http\Requests\Purchase;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'branch_id' => ['nullable', 'uuid', 'exists:branches,id'],
            'supplier_id' => ['nullable', 'uuid', 'exists:suppliers,id'],
            'supplier_invoice_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'purchased_at' => ['nullable', 'date'],
            'discount_amount' => ['nullable', 'numeric', 'min:0', 'decimal:0,4'],
            'tax_amount' => ['nullable', 'numeric', 'min:0', 'decimal:0,4'],
            'idempotency_key' => ['nullable', 'string', 'max:100'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'uuid', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,4'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0', 'decimal:0,4'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0', 'decimal:0,4'],
            'payment' => ['nullable', 'array'],
            'payment.method' => ['required_with:payment', Rule::in(['cash', 'mobile_money', 'card', 'bank_transfer', 'credit', 'other'])],
            'payment.amount' => ['required_with:payment', 'numeric', 'gt:0', 'decimal:0,4'],
            'payment.reference' => ['nullable', 'string', 'max:100'],
        ];
    }
}
