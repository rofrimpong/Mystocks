<?php

namespace App\Http\Requests\Expense;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['required', 'uuid', 'exists:expense_categories,id'],
            'branch_id' => ['nullable', 'uuid', 'exists:branches,id'],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,4'],
            'description' => ['nullable', 'string', 'max:500'],
            'payment_method' => ['nullable', Rule::in(['cash', 'mobile_money', 'card', 'bank_transfer', 'other'])],
            'reference' => ['nullable', 'string', 'max:100'],
            'attachment_path' => ['nullable', 'string', 'max:500'],
            'expense_date' => ['nullable', 'date'],
        ];
    }
}
