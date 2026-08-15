<?php

namespace App\Http\Requests\Sync;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'device_id' => ['required', 'string', 'max:100'],
            'operations' => ['required', 'array', 'min:1', 'max:50'],
            'operations.*.idempotency_key' => ['required', 'string', 'max:100'],
            'operations.*.operation_type' => ['required', Rule::in(['sale', 'inventory_adjustment', 'opening_stock'])],
            'operations.*.payload' => ['required', 'array'],
            'operations.*.client_created_at' => ['nullable', 'date'],
        ];
    }
}
