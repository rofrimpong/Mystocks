<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\AdjustStockRequest;
use App\Http\Requests\Inventory\OpeningStockRequest;
use App\Http\Resources\InventoryBalanceResource;
use App\Http\Resources\InventoryMovementResource;
use App\Models\Business;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function __construct(
        private readonly InventoryService $inventoryService
    ) {}

    /**
     * List inventory balances for the current business (optionally filtered by branch).
     */
    public function balances(Request $request): JsonResponse
    {
        /** @var Business $business */
        $business = $request->attributes->get('current_business');

        $query = InventoryBalance::query()
            ->where('business_id', $business->id)
            ->with(['product', 'branch']);

        if ($request->filled('branch_id')) {
            $branchId = $request->input('branch_id');
            $business->branches()->where('id', $branchId)->firstOrFail();

            // Ensure tracked products with no movements yet still appear as zero/out-of-stock.
            $business->products()->where('track_inventory', true)->where('is_active', true)
                ->select(['id', 'business_id'])
                ->chunkById(200, function ($products) use ($business, $branchId) {
                    foreach ($products as $product) {
                        InventoryBalance::firstOrCreate(
                            ['branch_id' => $branchId, 'product_id' => $product->id],
                            ['business_id' => $business->id, 'quantity' => 0, 'reserved_quantity' => 0, 'average_cost' => 0]
                        );
                    }
                });

            $query->where('branch_id', $branchId);
        }

        if ($request->boolean('low_stock', false)) {
            $query->whereHas('product', function ($q) {
                $q->where('track_inventory', true)
                    ->whereColumn('inventory_balances.quantity', '<=', 'products.minimum_stock_level');
            });
        }

        if ($search = $request->input('search')) {
            $query->whereHas('product', function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('sku', 'ilike', "%{$search}%")
                    ->orWhere('barcode', 'ilike', "%{$search}%");
            });
        }

        $balances = $query->orderBy('updated_at', 'desc')
            ->paginate($request->integer('per_page', 25));

        return response()->json([
            'data' => InventoryBalanceResource::collection($balances),
            'meta' => [
                'current_page' => $balances->currentPage(),
                'last_page' => $balances->lastPage(),
                'per_page' => $balances->perPage(),
                'total' => $balances->total(),
            ],
        ]);
    }

    /**
     * Get balance for a specific product at a branch.
     */
    public function showBalance(Request $request, string $productId): JsonResponse
    {
        /** @var Business $business */
        $business = $request->attributes->get('current_business');

        $branchId = $request->input('branch_id')
            ?? $request->attributes->get('current_branch_id')
            ?? $request->header('X-Branch-Id');

        if (! $branchId) {
            return response()->json(['message' => 'branch_id is required.'], 422);
        }

        // Ensure product belongs to business
        $product = $business->products()->findOrFail($productId);

        $balance = $this->inventoryService->getBalance($branchId, $product->id);
        $balance->load(['product', 'branch']);

        return response()->json([
            'data' => new InventoryBalanceResource($balance),
        ]);
    }

    /**
     * List inventory movements (ledger).
     */
    public function movements(Request $request): JsonResponse
    {
        /** @var Business $business */
        $business = $request->attributes->get('current_business');

        $query = InventoryMovement::query()
            ->where('business_id', $business->id)
            ->with(['product', 'branch', 'user'])
            ->orderByDesc('occurred_at');

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->input('branch_id'));
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->input('product_id'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('from')) {
            $query->where('occurred_at', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->where('occurred_at', '<=', $request->input('to'));
        }

        $movements = $query->paginate($request->integer('per_page', 50));

        return response()->json([
            'data' => InventoryMovementResource::collection($movements),
            'meta' => [
                'current_page' => $movements->currentPage(),
                'last_page' => $movements->lastPage(),
                'per_page' => $movements->perPage(),
                'total' => $movements->total(),
            ],
        ]);
    }

    /**
     * Record opening stock.
     */
    public function openingStock(OpeningStockRequest $request): JsonResponse
    {
        /** @var Business $business */
        $business = $request->attributes->get('current_business');
        $data = $request->validated();

        $product = $business->products()->findOrFail($data['product_id']);

        // Ensure branch belongs to business
        $branch = $business->branches()->where('id', $data['branch_id'])->firstOrFail();

        $movement = $this->inventoryService->openingStock(
            businessId: $business->id,
            branchId: $branch->id,
            productId: $product->id,
            quantity: $data['quantity'],
            unitCost: $data['unit_cost'] ?? $product->buying_price,
            userId: $request->user()->id,
            reason: $data['reason'] ?? null
        );

        $balance = $this->inventoryService->getBalance($branch->id, $product->id);

        return response()->json([
            'message' => 'Opening stock recorded successfully.',
            'data' => [
                'movement' => new InventoryMovementResource($movement->load(['product', 'branch'])),
                'balance' => new InventoryBalanceResource($balance->load(['product', 'branch'])),
            ],
        ], 201);
    }

    /**
     * Perform a stock adjustment.
     */
    public function adjust(AdjustStockRequest $request): JsonResponse
    {
        /** @var Business $business */
        $business = $request->attributes->get('current_business');
        $data = $request->validated();

        $product = $business->products()->findOrFail($data['product_id']);
        $branch = $business->branches()->where('id', $data['branch_id'])->firstOrFail();

        $movement = $this->inventoryService->adjust(
            businessId: $business->id,
            branchId: $branch->id,
            productId: $product->id,
            direction: $data['direction'],
            quantity: $data['quantity'],
            userId: $request->user()->id,
            reason: $data['reason'] ?? null,
            unitCost: $data['unit_cost'] ?? null
        );

        $balance = $this->inventoryService->getBalance($branch->id, $product->id);

        return response()->json([
            'message' => 'Stock adjustment recorded successfully.',
            'data' => [
                'movement' => new InventoryMovementResource($movement->load(['product', 'branch'])),
                'balance' => new InventoryBalanceResource($balance->load(['product', 'branch'])),
            ],
        ], 201);
    }
}
