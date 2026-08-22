<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sale\StoreSaleRequest;
use App\Http\Resources\SaleResource;
use App\Models\Business;
use App\Models\Sale;
use App\Services\SaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SaleController extends Controller
{
    public function __construct(
        private readonly SaleService $saleService
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var Business $business */
        $business = $request->attributes->get('current_business');

        $query = Sale::query()
            ->where('business_id', $business->id)
            ->with(['customer', 'branch', 'cashier'])
            ->withCount('items')
            ->orderByDesc('sold_at');

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->input('branch_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->input('payment_status'));
        }

        if ($request->filled('cashier_id')) {
            $query->where('cashier_id', $request->input('cashier_id'));
        }

        if ($request->filled('from')) {
            $query->where('sold_at', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->where('sold_at', '<=', $request->input('to'));
        }

        $sales = $query->paginate($request->integer('per_page', 25));

        return response()->json([
            'data' => SaleResource::collection($sales),
            'meta' => [
                'current_page' => $sales->currentPage(),
                'last_page' => $sales->lastPage(),
                'per_page' => $sales->perPage(),
                'total' => $sales->total(),
            ],
        ]);
    }

    public function store(StoreSaleRequest $request): JsonResponse
    {
        /** @var Business $business */
        $business = $request->attributes->get('current_business');
        $data = $request->validated();

        $branchId = $data['branch_id']
            ?? $request->attributes->get('current_branch_id')
            ?? $request->header('X-Branch-Id');

        if (! $branchId) {
            return response()->json(['message' => 'branch_id is required.'], 422);
        }

        $branch = $business->branches()->where('id', $branchId)->firstOrFail();

        if (! empty($data['customer_id'])) {
            $customerExists = \App\Models\Customer::where('business_id', $business->id)
                ->where('id', $data['customer_id'])
                ->exists();
            if (! $customerExists) {
                return response()->json(['message' => 'Customer does not belong to this business.'], 422);
            }
        }

        $sale = $this->saleService->create([
            'business_id' => $business->id,
            'branch_id' => $branch->id,
            'cashier_id' => $request->user()->id,
            'customer_id' => $data['customer_id'] ?? null,
            'notes' => $data['notes'] ?? null,
            'sold_at' => $data['sold_at'] ?? null,
            'discount_amount' => $data['discount_amount'] ?? 0,
            'tax_amount' => $data['tax_amount'] ?? 0,
            'idempotency_key' => $data['idempotency_key'] ?? null,
            'device_id' => $data['device_id'] ?? null,
            'items' => $data['items'],
            'payment' => $data['payment'] ?? null,
        ]);

        return response()->json([
            'message' => 'Sale completed successfully.',
            'data' => new SaleResource($sale),
        ], 201);
    }

    public function cancel(Request $request, string $id): JsonResponse
    {
        /** @var Business $business */
        $business = $request->attributes->get("current_business");

        $sale = Sale::where("business_id", $business->id)->findOrFail($id);
        $sale = $this->saleService->cancel($sale, $request->user()->id);

        return response()->json([
            "message" => "Sale cancelled and stock restored.",
            "data" => new SaleResource($sale),
        ]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        /** @var Business $business */
        $business = $request->attributes->get('current_business');

        $sale = Sale::where('business_id', $business->id)
            ->with(['items.product', 'payments', 'customer', 'branch', 'cashier'])
            ->findOrFail($id);

        return response()->json([
            'data' => new SaleResource($sale),
        ]);
    }
}
