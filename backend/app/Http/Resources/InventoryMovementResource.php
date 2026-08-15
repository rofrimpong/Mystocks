<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'business_id' => $this->business_id,
            'branch_id' => $this->branch_id,
            'product_id' => $this->product_id,
            'type' => $this->type,
            'direction' => $this->direction,
            'quantity' => (string) $this->quantity,
            'unit_cost' => $this->unit_cost !== null ? (string) $this->unit_cost : null,
            'total_cost' => $this->total_cost !== null ? (string) $this->total_cost : null,
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'reference_number' => $this->reference_number,
            'user_id' => $this->user_id,
            'reason' => $this->reason,
            'metadata' => $this->metadata,
            'occurred_at' => $this->occurred_at?->toIso8601String(),
            'product' => new ProductResource($this->whenLoaded('product')),
            'branch' => new BranchResource($this->whenLoaded('branch')),
            'user' => new UserResource($this->whenLoaded('user')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
