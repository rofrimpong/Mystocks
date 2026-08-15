<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Expense\StoreExpenseRequest;
use App\Http\Requests\Expense\UpdateExpenseRequest;
use App\Http\Resources\ExpenseResource;
use App\Models\Business;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Business $business */
        $business = $request->attributes->get('current_business');

        $query = Expense::query()
            ->where('business_id', $business->id)
            ->with(['category', 'branch', 'createdBy'])
            ->orderByDesc('expense_date');

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->input('branch_id'));
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        if ($request->filled('from')) {
            $query->where('expense_date', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->where('expense_date', '<=', $request->input('to'));
        }

        $expenses = $query->paginate($request->integer('per_page', 25));

        return response()->json([
            'data' => ExpenseResource::collection($expenses),
            'meta' => [
                'current_page' => $expenses->currentPage(),
                'last_page' => $expenses->lastPage(),
                'per_page' => $expenses->perPage(),
                'total' => $expenses->total(),
            ],
        ]);
    }

    public function store(StoreExpenseRequest $request): JsonResponse
    {
        /** @var Business $business */
        $business = $request->attributes->get('current_business');
        $data = $request->validated();

        // Ensure category belongs to business
        $category = ExpenseCategory::where('business_id', $business->id)
            ->where('id', $data['category_id'])
            ->firstOrFail();

        if (! empty($data['branch_id'])) {
            $business->branches()->where('id', $data['branch_id'])->firstOrFail();
        }

        $expense = Expense::create([
            'business_id' => $business->id,
            'branch_id' => $data['branch_id'] ?? null,
            'category_id' => $category->id,
            'created_by' => $request->user()->id,
            'amount' => $data['amount'],
            'description' => $data['description'] ?? null,
            'payment_method' => $data['payment_method'] ?? 'cash',
            'reference' => $data['reference'] ?? null,
            'attachment_path' => $data['attachment_path'] ?? null,
            'expense_date' => $data['expense_date'] ?? now()->toDateString(),
        ]);

        return response()->json([
            'message' => 'Expense recorded successfully.',
            'data' => new ExpenseResource($expense->load(['category', 'branch'])),
        ], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        /** @var Business $business */
        $business = $request->attributes->get('current_business');

        $expense = Expense::where('business_id', $business->id)
            ->with(['category', 'branch', 'createdBy'])
            ->findOrFail($id);

        return response()->json([
            'data' => new ExpenseResource($expense),
        ]);
    }

    public function update(UpdateExpenseRequest $request, string $id): JsonResponse
    {
        /** @var Business $business */
        $business = $request->attributes->get('current_business');

        $expense = Expense::where('business_id', $business->id)->findOrFail($id);
        $expense->update($request->validated());

        return response()->json([
            'message' => 'Expense updated successfully.',
            'data' => new ExpenseResource($expense->fresh()->load(['category', 'branch'])),
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        /** @var Business $business */
        $business = $request->attributes->get('current_business');

        $expense = Expense::where('business_id', $business->id)->findOrFail($id);
        $expense->delete();

        return response()->json([
            'message' => 'Expense deleted successfully.',
        ]);
    }
}
