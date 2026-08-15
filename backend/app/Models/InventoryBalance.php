<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryBalance extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'business_id',
        'branch_id',
        'product_id',
        'quantity',
        'reserved_quantity',
        'average_cost',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'reserved_quantity' => 'decimal:4',
            'average_cost' => 'decimal:4',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function availableQuantity(): string
    {
        return bcsub((string) $this->quantity, (string) $this->reserved_quantity, 4);
    }

    public function isLowStock(): bool
    {
        if (! $this->product || ! $this->product->track_inventory) {
            return false;
        }

        return bccomp((string) $this->quantity, (string) $this->product->minimum_stock_level, 4) <= 0;
    }
}
