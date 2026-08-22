<?php

namespace App\Services;

use App\Models\IdempotencyKey;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SyncOperation;
use Illuminate\Validation\ValidationException;

class SyncService
{
    public function __construct(
        private readonly SaleService $saleService,
        private readonly InventoryService $inventoryService
    ) {}

    /**
     * Process a batch of offline operations.
     * Each operation is handled independently so one failure does not block others.
     *
     * @param  array{
     *     business_id: string,
     *     user_id: string,
     *     device_id: string,
     *     operations: array<int, array{
     *         idempotency_key: string,
     *         operation_type: string,
     *         payload: array,
     *         client_created_at?: string
     *     }>
     * }  $batch
     * @return array{results: array<int, array>}
     */
    public function processBatch(array $batch): array
    {
        $results = [];

        foreach ($batch['operations'] as $operation) {
            $results[] = $this->processSingle(
                businessId: $batch['business_id'],
                userId: $batch['user_id'],
                deviceId: $batch['device_id'],
                operation: $operation
            );
        }

        return ['results' => $results];
    }

    private function processSingle(
        string $businessId,
        string $userId,
        string $deviceId,
        array $operation
    ): array {
        $key = $operation['idempotency_key'];
        $type = $operation['operation_type'];
        $payload = $operation['payload'] ?? [];

        // 1. Check existing idempotency key – never create duplicates
        $existing = IdempotencyKey::where('business_id', $businessId)
            ->where('key', $key)
            ->first();

        if ($existing) {
            if ($existing->status === 'completed') {
                return [
                    'idempotency_key' => $key,
                    'status' => 'already_synced',
                    'resource_id' => $existing->resource_id,
                    'message' => 'Operation was already processed successfully.',
                    'server_result' => $existing->response_payload,
                ];
            }

            if ($existing->status === 'conflict') {
                return [
                    'idempotency_key' => $key,
                    'status' => 'conflict',
                    'message' => 'Operation previously resulted in a conflict.',
                    'conflict_reason' => $existing->response_payload['conflict_reason'] ?? 'Previous conflict',
                    'server_result' => $existing->response_payload,
                ];
            }
        }

        // Record / update sync operation
        $syncOp = SyncOperation::firstOrNew([
            'business_id' => $businessId,
            'idempotency_key' => $key,
        ]);
        $syncOp->fill([
            'user_id' => $userId,
            'device_id' => $deviceId,
            'operation_type' => $type,
            'status' => 'pending',
            'payload' => $payload,
            'client_created_at' => $operation['client_created_at'] ?? now(),
            'retry_count' => ((int) $syncOp->retry_count) + 1,
        ]);
        $syncOp->save();

        try {
            $result = match ($type) {
                'sale' => $this->handleSale($businessId, $userId, $deviceId, $key, $payload),
                'inventory_adjustment' => $this->handleInventoryAdjustment($businessId, $userId, $key, $payload),
                'opening_stock' => $this->handleOpeningStock($businessId, $userId, $key, $payload),
                default => throw ValidationException::withMessages([
                    'operation_type' => ["Unsupported offline operation type: {$type}"],
                ]),
            };

            $syncOp->update([
                'status' => 'synced',
                'server_result' => $result,
                'synced_at' => now(),
            ]);

            return [
                'idempotency_key' => $key,
                'status' => 'synced',
                'resource_id' => $result['resource_id'] ?? null,
                'message' => 'Operation synced successfully.',
                'server_result' => $result,
            ];
        } catch (ValidationException $e) {
            $conflictReason = collect($e->errors())->flatten()->first() ?? 'Validation failed';

            $syncOp->update([
                'status' => 'conflict',
                'conflict_reason' => $conflictReason,
                'server_result' => ['errors' => $e->errors()],
                'synced_at' => now(),
            ]);

            IdempotencyKey::updateOrCreate(
                ['business_id' => $businessId, 'key' => $key],
                [
                    'business_id' => $businessId,
                    'operation_type' => $type,
                    'status' => 'conflict',
                    'request_payload' => $payload,
                    'response_payload' => [
                        'conflict_reason' => $conflictReason,
                        'errors' => $e->errors(),
                    ],
                    'device_id' => $deviceId,
                    'user_id' => $userId,
                ]
            );

            return [
                'idempotency_key' => $key,
                'status' => 'conflict',
                'message' => $conflictReason,
                'conflict_reason' => $conflictReason,
                'errors' => $e->errors(),
            ];
        } catch (\Throwable $e) {
            $syncOp->update([
                'status' => 'failed',
                'conflict_reason' => $e->getMessage(),
                'synced_at' => now(),
            ]);

            return [
                'idempotency_key' => $key,
                'status' => 'failed',
                'message' => 'Server error while processing operation.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal error',
            ];
        }
    }

    private function handleSale(
        string $businessId,
        string $userId,
        string $deviceId,
        string $idempotencyKey,
        array $payload
    ): array {
        $this->assertBranchBelongsToBusiness($businessId, $payload['branch_id'] ?? null);

        if (! empty($payload['customer_id'])) {
            $this->assertCustomerBelongsToBusiness($businessId, $payload['customer_id']);
        }

        foreach ($payload['items'] ?? [] as $index => $item) {
            $productId = $item['product_id'] ?? null;
            if (! $productId || ! Product::where('business_id', $businessId)->where('id', $productId)->exists()) {
                throw ValidationException::withMessages([
                    "items.{$index}.product_id" => ['Product does not belong to this business.'],
                ]);
            }
        }

        // Mark idempotency as processing
        IdempotencyKey::updateOrCreate(
            ['business_id' => $businessId, 'key' => $idempotencyKey],
            [
                'business_id' => $businessId,
                'operation_type' => 'sale',
                'status' => 'processing',
                'request_payload' => $payload,
                'device_id' => $deviceId,
                'user_id' => $userId,
            ]
        );

        $sale = $this->saleService->create([
            'business_id' => $businessId,
            'branch_id' => $payload['branch_id'],
            'cashier_id' => $userId,
            'customer_id' => $payload['customer_id'] ?? null,
            'notes' => $payload['notes'] ?? null,
            'sold_at' => $payload['sold_at'] ?? null,
            'discount_amount' => $payload['discount_amount'] ?? 0,
            'tax_amount' => $payload['tax_amount'] ?? 0,
            'idempotency_key' => $idempotencyKey,
            'device_id' => $deviceId,
            'items' => $payload['items'] ?? [],
            'payment' => $payload['payment'] ?? null,
        ]);

        IdempotencyKey::where('business_id', $businessId)->where('key', $idempotencyKey)->update([
            'status' => 'completed',
            'resource_id' => $sale->id,
            'response_payload' => [
                'sale_id' => $sale->id,
                'sale_number' => $sale->sale_number,
                'total' => (string) $sale->total,
            ],
        ]);

        return [
            'resource_id' => $sale->id,
            'sale_id' => $sale->id,
            'sale_number' => $sale->sale_number,
            'total' => (string) $sale->total,
            'payment_status' => $sale->payment_status,
        ];
    }

    private function handleInventoryAdjustment(
        string $businessId,
        string $userId,
        string $idempotencyKey,
        array $payload
    ): array {
        $this->assertBranchBelongsToBusiness($businessId, $payload['branch_id'] ?? null);
        $this->assertProductBelongsToBusiness($businessId, $payload['product_id'] ?? null);

        IdempotencyKey::updateOrCreate(
            ['business_id' => $businessId, 'key' => $idempotencyKey],
            [
                'business_id' => $businessId,
                'operation_type' => 'inventory_adjustment',
                'status' => 'processing',
                'request_payload' => $payload,
                'user_id' => $userId,
            ]
        );

        $movement = $this->inventoryService->adjust(
            businessId: $businessId,
            branchId: $payload['branch_id'],
            productId: $payload['product_id'],
            direction: $payload['direction'],
            quantity: $payload['quantity'],
            userId: $userId,
            reason: $payload['reason'] ?? 'Offline adjustment',
            unitCost: $payload['unit_cost'] ?? null
        );

        IdempotencyKey::where('business_id', $businessId)->where('key', $idempotencyKey)->update([
            'status' => 'completed',
            'resource_id' => $movement->id,
            'response_payload' => ['movement_id' => $movement->id],
        ]);

        return [
            'resource_id' => $movement->id,
            'movement_id' => $movement->id,
        ];
    }

    private function handleOpeningStock(
        string $businessId,
        string $userId,
        string $idempotencyKey,
        array $payload
    ): array {
        $this->assertBranchBelongsToBusiness($businessId, $payload['branch_id'] ?? null);
        $this->assertProductBelongsToBusiness($businessId, $payload['product_id'] ?? null);

        IdempotencyKey::updateOrCreate(
            ['business_id' => $businessId, 'key' => $idempotencyKey],
            [
                'business_id' => $businessId,
                'operation_type' => 'opening_stock',
                'status' => 'processing',
                'request_payload' => $payload,
                'user_id' => $userId,
            ]
        );

        $movement = $this->inventoryService->openingStock(
            businessId: $businessId,
            branchId: $payload['branch_id'],
            productId: $payload['product_id'],
            quantity: $payload['quantity'],
            unitCost: $payload['unit_cost'] ?? null,
            userId: $userId,
            reason: $payload['reason'] ?? 'Offline opening stock'
        );

        IdempotencyKey::where('business_id', $businessId)->where('key', $idempotencyKey)->update([
            'status' => 'completed',
            'resource_id' => $movement->id,
            'response_payload' => ['movement_id' => $movement->id],
        ]);

        return [
            'resource_id' => $movement->id,
            'movement_id' => $movement->id,
        ];
    }

    private function assertBranchBelongsToBusiness(string $businessId, mixed $branchId): void
    {
        if (! is_string($branchId) || ! Branch::where('business_id', $businessId)
            ->where('id', $branchId)
            ->where('status', 'active')
            ->exists()) {
            throw ValidationException::withMessages([
                'branch_id' => ['Branch does not belong to this business or is inactive.'],
            ]);
        }
    }

    private function assertProductBelongsToBusiness(string $businessId, mixed $productId): void
    {
        if (! is_string($productId) || ! Product::where('business_id', $businessId)
            ->where('id', $productId)
            ->where('is_active', true)
            ->exists()) {
            throw ValidationException::withMessages([
                'product_id' => ['Product does not belong to this business or is inactive.'],
            ]);
        }
    }

    private function assertCustomerBelongsToBusiness(string $businessId, mixed $customerId): void
    {
        if (! is_string($customerId) || ! Customer::where('business_id', $businessId)
            ->where('id', $customerId)
            ->where('status', 'active')
            ->exists()) {
            throw ValidationException::withMessages([
                'customer_id' => ['Customer does not belong to this business or is inactive.'],
            ]);
        }
    }
}
