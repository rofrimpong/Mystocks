<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Supplier\StoreSupplierRequest;
use App\Http\Requests\Supplier\UpdateSupplierRequest;
use App\Http\Resources\SupplierResource;
use App\Models\Business;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Business $business */
        $business = $request->attributes->get('current_business');

        $query = Supplier::query()
            ->where('business_id', $business->id)
            ->orderBy('name');

        if ($request->boolean('active_only', false)) {
            $query->where('status', 'active');
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('company', 'ilike', "%{$search}%")
                    ->orWhere('phone', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%");
            });
        }

        if ($request->boolean('with_balance', false)) {
            $query->where('outstanding_balance', '>', 0);
        }

        $suppliers = $query->paginate($request->integer('per_page', 25));

        return response()->json([
            'data' => SupplierResource::collection($suppliers),
            'meta' => [
                'current_page' => $suppliers->currentPage(),
                'last_page' => $suppliers->lastPage(),
                'per_page' => $suppliers->perPage(),
                'total' => $suppliers->total(),
            ],
        ]);
    }

    public function store(StoreSupplierRequest $request): JsonResponse
    {
        /** @var Business $business */
        $business = $request->attributes->get('current_business');

        $data = $request->validated();
        $data['business_id'] = $business->id;

        $supplier = Supplier::create($data);

        return response()->json([
            'message' => 'Supplier created successfully.',
            'data' => new SupplierResource($supplier),
        ], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        /** @var Business $business */
        $business = $request->attributes->get('current_business');

        $supplier = Supplier::where('business_id', $business->id)->findOrFail($id);

        return response()->json([
            'data' => new SupplierResource($supplier),
        ]);
    }

    public function update(UpdateSupplierRequest $request, string $id): JsonResponse
    {
        /** @var Business $business */
        $business = $request->attributes->get('current_business');

        $supplier = Supplier::where('business_id', $business->id)->findOrFail($id);
        $supplier->update($request->validated());

        return response()->json([
            'message' => 'Supplier updated successfully.',
            'data' => new SupplierResource($supplier->fresh()),
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        /** @var Business $business */
        $business = $request->attributes->get('current_business');

        $supplier = Supplier::where('business_id', $business->id)->findOrFail($id);

        if (bccomp((string) $supplier->outstanding_balance, '0', 4) > 0) {
            return response()->json([
                'message' => 'Cannot delete supplier with outstanding balance. Clear the balance first.',
            ], 422);
        }

        $supplier->delete();

        return response()->json([
            'message' => 'Supplier deleted successfully.',
        ]);
    }
}
