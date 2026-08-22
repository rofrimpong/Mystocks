<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'business_id' => $this->business_id,
            'branch_id' => $this->branch_id,
            'cashier_id' => $this->cashier_id,
            'customer_id' => $this->customer_id,
            'sale_number' => $this->sale_number,
            'status' => $this->status,
            'subtotal' => (string) $this->subtotal,
            'discount_amount' => (string) $this->discount_amount,
            'tax_amount' => (string) $this->tax_amount,
            'total' => (string) $this->total,
            'cost_of_goods' => (string) $this->cost_of_goods,
            'gross_profit' => (string) $this->gross_profit,
            'payment_status' => $this->payment_status,
            'item_count' => $this->when(
                isset($this->items_count) || $this->relationLoaded('items'),
                fn () => isset($this->items_count) ? (int) $this->items_count : $this->items->count()
            ),
            'notes' => $this->notes,
            'device_id' => $this->device_id,
            'sold_at' => $this->sold_at?->toIso8601String(),
            'customer' => $this->whenLoaded('customer', fn () => [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
                'phone' => $this->customer->phone,
            ]),
            'branch' => new BranchResource($this->whenLoaded('branch')),
            'cashier' => $this->whenLoaded('cashier', fn () => [
                'id' => $this->cashier->id,
                'name' => $this->cashier->name,
            ]),
            'items' => $this->whenLoaded('items', function () {
                return $this->items->map(fn ($item) => [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product_name,
                    'product_sku' => $item->product_sku,
                    'quantity' => (string) $item->quantity,
                    'unit_selling_price' => (string) $item->unit_selling_price,
                    'unit_cost_price' => (string) $item->unit_cost_price,
                    'discount_amount' => (string) $item->discount_amount,
                    'line_total' => (string) $item->line_total,
                    'line_cost' => (string) $item->line_cost,
                    'line_profit' => (string) $item->line_profit,
                ]);
            }),
            'payments' => $this->whenLoaded('payments', function () {
                return $this->payments->map(fn ($p) => [
                    'id' => $p->id,
                    'method' => $p->method,
                    'amount' => (string) $p->amount,
                    'reference' => $p->reference,
                    'provider' => $p->provider,
                    'paid_at' => $p->paid_at?->toIso8601String(),
                ]);
            }),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
