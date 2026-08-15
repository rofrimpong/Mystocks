<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'business_id' => $this->business_id,
            'category_id' => $this->category_id,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'name' => $this->name,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'brand' => $this->brand,
            'unit' => $this->unit,
            'description' => $this->description,
            'image_path' => $this->image_path,
            'buying_price' => (string) $this->buying_price,
            'selling_price' => (string) $this->selling_price,
            'minimum_stock_level' => (string) $this->minimum_stock_level,
            'track_inventory' => (bool) $this->track_inventory,
            'is_active' => (bool) $this->is_active,
            'is_service' => (bool) $this->is_service,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
