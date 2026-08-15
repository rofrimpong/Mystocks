<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'business_id' => $this->business_id,
            'branch_id' => $this->branch_id,
            'category_id' => $this->category_id,
            'amount' => (string) $this->amount,
            'description' => $this->description,
            'payment_method' => $this->payment_method,
            'reference' => $this->reference,
            'attachment_path' => $this->attachment_path,
            'expense_date' => $this->expense_date?->toDateString(),
            'category' => new ExpenseCategoryResource($this->whenLoaded('category')),
            'branch' => new BranchResource($this->whenLoaded('branch')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
