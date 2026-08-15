<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'business_id' => $this->business_id,
            'branch_id' => $this->branch_id,
            'supplier_id' => $this->supplier_id,
            'purchase_number' => $this->purchase_number,
            'supplier_invoice_number' => $this->supplier_invoice_number,
            'status' => $this->status,
            'subtotal' => (string) $this->subtotal,
            'discount_amount' => (string) $this->discount_amount,
            'tax_amount' => (string) $this->tax_amount,
            'total' => (string) $this->total,
            'payment_status' => $this->payment_status,
            'notes' => $this->notes,
            'purchased_at' => $this->purchased_at?->toIso8601String(),
            'supplier' => $this->whenLoaded('supplier', fn () => [
                'id' => $this->supplier->id,
                'name' => $this->supplier->name,
                'company' => $this->supplier->company,
            ]),
            'branch' => new BranchResource($this->whenLoaded('branch')),
            'items' => $this->whenLoaded('items', function () {
                return $this->items->map(fn ($item) => [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product_name,
                    'product_sku' => $item->product_sku,
                    'quantity' => (string) $item->quantity,
                    'unit_cost' => (string) $item->unit_cost,
                    'discount_amount' => (string) $item->discount_amount,
                    'line_total' => (string) $item->line_total,
                ]);
            }),
            'payments' => $this->whenLoaded('payments', function () {
                return $this->payments->map(fn ($p) => [
                    'id' => $p->id,
                    'method' => $p->method,
                    'amount' => (string) $p->amount,
                    'reference' => $p->reference,
                    'paid_at' => $p->paid_at?->toIso8601String(),
                ]);
            }),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
