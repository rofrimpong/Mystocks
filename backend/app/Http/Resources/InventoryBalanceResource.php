<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryBalanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'business_id' => $this->business_id,
            'branch_id' => $this->branch_id,
            'product_id' => $this->product_id,
            'quantity' => (string) $this->quantity,
            'reserved_quantity' => (string) $this->reserved_quantity,
            'available_quantity' => $this->availableQuantity(),
            'average_cost' => (string) $this->average_cost,
            'is_low_stock' => $this->isLowStock(),
            'product' => new ProductResource($this->whenLoaded('product')),
            'branch' => new BranchResource($this->whenLoaded('branch')),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
