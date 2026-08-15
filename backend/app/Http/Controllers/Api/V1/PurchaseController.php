<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Purchase\StorePurchaseRequest;
use App\Http\Resources\PurchaseResource;
use App\Models\Business;
use App\Models\Purchase;
use App\Services\PurchaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseController extends Controller
{
    public function __construct(
        private readonly PurchaseService $purchaseService
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var Business $business */
        $business = $request->attributes->get('current_business');

        $query = Purchase::query()
            ->where('business_id', $business->id)
            ->with(['supplier', 'branch', 'createdBy'])
            ->orderByDesc('purchased_at');

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->input('branch_id'));
        }

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->input('supplier_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->input('payment_status'));
        }

        if ($request->filled('from')) {
            $query->where('purchased_at', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->where('purchased_at', '<=', $request->input('to'));
        }

        $purchases = $query->paginate($request->integer('per_page', 25));

        return response()->json([
            'data' => PurchaseResource::collection($purchases),
            'meta' => [
                'current_page' => $purchases->currentPage(),
                'last_page' => $purchases->lastPage(),
                'per_page' => $purchases->perPage(),
                'total' => $purchases->total(),
            ],
        ]);
    }

    public function store(StorePurchaseRequest $request): JsonResponse
    {
        /** @var Business $business */
        $business = $request->attributes->get('current_business');
        $data = $request->validated();

        // Resolve branch
        $branchId = $data['branch_id']
            ?? $request->attributes->get('current_branch_id')
            ?? $request->header('X-Branch-Id');

        if (! $branchId) {
            return response()->json(['message' => 'branch_id is required.'], 422);
        }

        $branch = $business->branches()->where('id', $branchId)->firstOrFail();

        // Validate supplier belongs to business if provided
        if (! empty($data['supplier_id'])) {
            $supplierExists = $business->suppliers()->where('id', $data['supplier_id'])->exists();
            // suppliers relation may not exist yet on Business – fallback
            if (! $supplierExists) {
                $supplierExists = \App\Models\Supplier::where('business_id', $business->id)
                    ->where('id', $data['supplier_id'])
                    ->exists();
            }
            if (! $supplierExists) {
                return response()->json(['message' => 'Supplier does not belong to this business.'], 422);
            }
        }

        $purchase = $this->purchaseService->create([
            'business_id' => $business->id,
            'branch_id' => $branch->id,
            'supplier_id' => $data['supplier_id'] ?? null,
            'created_by' => $request->user()->id,
            'supplier_invoice_number' => $data['supplier_invoice_number'] ?? null,
            'notes' => $data['notes'] ?? null,
            'purchased_at' => $data['purchased_at'] ?? null,
            'discount_amount' => $data['discount_amount'] ?? 0,
            'tax_amount' => $data['tax_amount'] ?? 0,
            'idempotency_key' => $data['idempotency_key'] ?? null,
            'items' => $data['items'],
            'payment' => $data['payment'] ?? null,
        ]);

        return response()->json([
            'message' => 'Purchase recorded successfully. Inventory updated.',
            'data' => new PurchaseResource($purchase),
        ], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        /** @var Business $business */
        $business = $request->attributes->get('current_business');

        $purchase = Purchase::where('business_id', $business->id)
            ->with(['items.product', 'payments', 'supplier', 'branch', 'createdBy'])
            ->findOrFail($id);

        return response()->json([
            'data' => new PurchaseResource($purchase),
        ]);
    }
}
