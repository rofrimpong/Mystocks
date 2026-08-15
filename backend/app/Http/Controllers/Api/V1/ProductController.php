<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Business;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Business $business */
        $business = $request->attributes->get('current_business');

        $query = $business->products()
            ->with('category')
            ->orderBy('name');

        if ($request->boolean('active_only', false)) {
            $query->where('is_active', true);
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('sku', 'ilike', "%{$search}%")
                    ->orWhere('barcode', 'ilike', "%{$search}%")
                    ->orWhere('brand', 'ilike', "%{$search}%");
            });
        }

        if ($request->boolean('low_stock', false)) {
            // Will be refined when inventory balances are joined in later phase
            $query->where('track_inventory', true)
                ->where('minimum_stock_level', '>', 0);
        }

        $products = $query->paginate($request->integer('per_page', 25));

        return response()->json([
            'data' => ProductResource::collection($products),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
        ]);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        /** @var Business $business */
        $business = $request->attributes->get('current_business');

        $data = $request->validated();
        $data['business_id'] = $business->id;

        // Validate category belongs to same business
        if (! empty($data['category_id'])) {
            $categoryExists = $business->categories()->where('id', $data['category_id'])->exists();
            if (! $categoryExists) {
                return response()->json(['message' => 'Category does not belong to this business.'], 422);
            }
        }

        $product = Product::create($data);

        return response()->json([
            'message' => 'Product created successfully.',
            'data' => new ProductResource($product->load('category')),
        ], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        /** @var Business $business */
        $business = $request->attributes->get('current_business');

        $product = $business->products()->with('category')->findOrFail($id);

        return response()->json([
            'data' => new ProductResource($product),
        ]);
    }

    public function update(UpdateProductRequest $request, string $id): JsonResponse
    {
        /** @var Business $business */
        $business = $request->attributes->get('current_business');

        $product = $business->products()->findOrFail($id);
        $data = $request->validated();

        if (! empty($data['category_id'])) {
            $categoryExists = $business->categories()->where('id', $data['category_id'])->exists();
            if (! $categoryExists) {
                return response()->json(['message' => 'Category does not belong to this business.'], 422);
            }
        }

        // SKU uniqueness check within business (excluding self)
        if (isset($data['sku']) && $data['sku'] !== null) {
            $skuExists = $business->products()
                ->where('sku', $data['sku'])
                ->where('id', '!=', $product->id)
                ->exists();
            if ($skuExists) {
                return response()->json(['message' => 'SKU already exists for this business.'], 422);
            }
        }

        $product->update($data);

        return response()->json([
            'message' => 'Product updated successfully.',
            'data' => new ProductResource($product->fresh()->load('category')),
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        /** @var Business $business */
        $business = $request->attributes->get('current_business');

        $product = $business->products()->findOrFail($id);

        // Soft delete – inventory history and past sales remain intact
        $product->delete();

        return response()->json([
            'message' => 'Product deleted successfully.',
        ]);
    }
}
