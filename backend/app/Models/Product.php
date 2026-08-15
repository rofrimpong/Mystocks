<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'business_id',
        'category_id',
        'name',
        'sku',
        'barcode',
        'brand',
        'unit',
        'description',
        'image_path',
        'buying_price',
        'selling_price',
        'minimum_stock_level',
        'track_inventory',
        'is_active',
        'is_service',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'buying_price' => 'decimal:4',
            'selling_price' => 'decimal:4',
            'minimum_stock_level' => 'decimal:4',
            'track_inventory' => 'boolean',
            'is_active' => 'boolean',
            'is_service' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function inventoryBalances(): HasMany
    {
        return $this->hasMany(InventoryBalance::class);
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }
}
