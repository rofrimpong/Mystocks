<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExpenseCategory\StoreExpenseCategoryRequest;
use App\Http\Resources\ExpenseCategoryResource;
use App\Models\Business;
use App\Models\ExpenseCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ExpenseCategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Business $business */
        $business = $request->attributes->get('current_business');

        $categories = ExpenseCategory::where('business_id', $business->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => ExpenseCategoryResource::collection($categories),
        ]);
    }

    public function store(StoreExpenseCategoryRequest $request): JsonResponse
    {
        /** @var Business $business */
        $business = $request->attributes->get('current_business');
        $data = $request->validated();

        $slug = Str::slug($data['name']);
        $base = $slug;
        $i = 1;
        while (ExpenseCategory::where('business_id', $business->id)->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }

        $category = ExpenseCategory::create([
            'business_id' => $business->id,
            'name' => $data['name'],
            'slug' => $slug,
            'is_system' => false,
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'Expense category created successfully.',
            'data' => new ExpenseCategoryResource($category),
        ], 201);
    }
}
