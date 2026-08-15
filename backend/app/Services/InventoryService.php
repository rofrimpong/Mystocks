<?php

namespace App\Services;

use App\Models\Business;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class InventoryService
{
    /**
     * Apply an inventory movement and update the balance atomically.
     *
     * @param  array{
     *     business_id: string,
     *     branch_id: string,
     *     product_id: string,
     *     type: string,
     *     direction: 'in'|'out',
     *     quantity: string|float|int,
     *     unit_cost?: string|float|null,
     *     reference_type?: string|null,
     *     reference_id?: string|null,
     *     reference_number?: string|null,
     *     user_id?: string|null,
     *     reason?: string|null,
     *     metadata?: array|null,
     *     occurred_at?: \DateTimeInterface|string|null,
     * }  $data
     */
    public function move(array $data): InventoryMovement
    {
        $quantity = $this->normalizeDecimal($data['quantity']);

        if (bccomp($quantity, '0', 4) <= 0) {
            throw ValidationException::withMessages([
                'quantity' => ['Quantity must be greater than zero.'],
            ]);
        }

        return DB::transaction(function () use ($data, $quantity) {
            // Lock the balance row (or create it)
            $balance = InventoryBalance::where('branch_id', $data['branch_id'])
                ->where('product_id', $data['product_id'])
                ->lockForUpdate()
                ->first();

            if (! $balance) {
                $balance = InventoryBalance::create([
                    'business_id' => $data['business_id'],
                    'branch_id' => $data['branch_id'],
                    'product_id' => $data['product_id'],
                    'quantity' => '0',
                    'reserved_quantity' => '0',
                    'average_cost' => '0',
                ]);

                // Re-lock after create
                $balance = InventoryBalance::where('id', $balance->id)->lockForUpdate()->firstOrFail();
            }

            $direction = $data['direction'];
            $currentQty = (string) $balance->quantity;

            if ($direction === 'out') {
                $newQty = bcsub($currentQty, $quantity, 4);

                // Negative stock protection
                $business = Business::findOrFail($data['business_id']);
                if (bccomp($newQty, '0', 4) < 0 && ! $business->allow_negative_stock) {
                    $product = Product::find($data['product_id']);
                    throw ValidationException::withMessages([
                        'quantity' => [
                            sprintf(
                                'Insufficient stock for "%s". Available: %s, requested: %s.',
                                $product?->name ?? 'product',
                                $currentQty,
                                $quantity
                            ),
                        ],
                    ]);
                }
            } else {
                $newQty = bcadd($currentQty, $quantity, 4);
            }

            // Average cost calculation for inbound movements with cost
            $unitCost = isset($data['unit_cost']) ? $this->normalizeDecimal($data['unit_cost']) : null;
            $totalCost = null;

            if ($direction === 'in' && $unitCost !== null && bccomp($unitCost, '0', 4) >= 0) {
                $totalCost = bcmul($quantity, $unitCost, 4);
                $currentValue = bcmul($currentQty, (string) $balance->average_cost, 4);
                $newValue = bcadd($currentValue, $totalCost, 4);

                if (bccomp($newQty, '0', 4) > 0) {
                    $balance->average_cost = bcdiv($newValue, $newQty, 4);
                }
            } elseif ($direction === 'out') {
                // Outbound uses current average cost if not provided
                $unitCost = $unitCost ?? (string) $balance->average_cost;
                $totalCost = bcmul($quantity, $unitCost, 4);
            }

            $balance->quantity = $newQty;
            $balance->save();

            $movement = InventoryMovement::create([
                'business_id' => $data['business_id'],
                'branch_id' => $data['branch_id'],
                'product_id' => $data['product_id'],
                'type' => $data['type'],
                'direction' => $direction,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'total_cost' => $totalCost,
                'reference_type' => $data['reference_type'] ?? null,
                'reference_id' => $data['reference_id'] ?? null,
                'reference_number' => $data['reference_number'] ?? null,
                'user_id' => $data['user_id'] ?? null,
                'reason' => $data['reason'] ?? null,
                'metadata' => $data['metadata'] ?? null,
                'occurred_at' => $data['occurred_at'] ?? now(),
            ]);

            return $movement;
        });
    }

    /**
     * Record opening stock for a product at a branch.
     */
    public function openingStock(
        string $businessId,
        string $branchId,
        string $productId,
        string|float|int $quantity,
        string|float|null $unitCost = null,
        ?string $userId = null,
        ?string $reason = null
    ): InventoryMovement {
        return $this->move([
            'business_id' => $businessId,
            'branch_id' => $branchId,
            'product_id' => $productId,
            'type' => 'opening_stock',
            'direction' => 'in',
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'user_id' => $userId,
            'reason' => $reason ?? 'Opening stock',
        ]);
    }

    /**
     * Stock adjustment (can be in or out).
     */
    public function adjust(
        string $businessId,
        string $branchId,
        string $productId,
        string $direction,
        string|float|int $quantity,
        ?string $userId = null,
        ?string $reason = null,
        string|float|null $unitCost = null
    ): InventoryMovement {
        if (! in_array($direction, ['in', 'out'], true)) {
            throw new InvalidArgumentException('Direction must be "in" or "out".');
        }

        return $this->move([
            'business_id' => $businessId,
            'branch_id' => $branchId,
            'product_id' => $productId,
            'type' => 'adjustment',
            'direction' => $direction,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'user_id' => $userId,
            'reason' => $reason ?? 'Stock adjustment',
        ]);
    }

    /**
     * Get current balance for a product at a branch (creates zero balance if missing).
     */
    public function getBalance(string $branchId, string $productId): InventoryBalance
    {
        return InventoryBalance::firstOrCreate(
            [
                'branch_id' => $branchId,
                'product_id' => $productId,
            ],
            [
                'business_id' => Product::findOrFail($productId)->business_id,
                'quantity' => '0',
                'reserved_quantity' => '0',
                'average_cost' => '0',
            ]
        );
    }

    private function normalizeDecimal(string|float|int $value): string
    {
        return number_format((float) $value, 4, '.', '');
    }
}
