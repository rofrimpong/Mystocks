<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Branch\StoreBranchRequest;
use App\Http\Requests\Branch\UpdateBranchRequest;
use App\Http\Resources\BranchResource;
use App\Models\Branch;
use App\Models\Business;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    /**
     * List branches for the current business.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var Business $business */
        $business = $request->attributes->get('current_business');

        if (! $business) {
            return response()->json(['message' => 'Business context required.'], 400);
        }

        $branches = $business->branches()
            ->orderByDesc('is_head_office')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => BranchResource::collection($branches),
        ]);
    }

    /**
     * Create a new branch (owner / multi-branch enabled).
     */
    public function store(StoreBranchRequest $request): JsonResponse
    {
        /** @var Business $business */
        $business = $request->attributes->get('current_business');

        if (! $business) {
            return response()->json(['message' => 'Business context required.'], 400);
        }

        $isOwner = $request->attributes->get('is_business_owner', false)
            || $request->user()->isPlatformAdmin();

        if (! $isOwner) {
            return response()->json([
                'message' => 'Only the business owner can create branches.',
            ], 403);
        }

        // Subscription / plan check can be added later
        if (! $business->multi_branch_enabled) {
            // Still allow creation but warn; enforcement can be stricter later
        }

        $data = $request->validated();
        $data['business_id'] = $business->id;

        $branch = Branch::create($data);

        return response()->json([
            'message' => 'Branch created successfully.',
            'data' => new BranchResource($branch),
        ], 201);
    }

    /**
     * Show a single branch.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        /** @var Business $business */
        $business = $request->attributes->get('current_business');

        $branch = $business->branches()->where('id', $id)->firstOrFail();

        return response()->json([
            'data' => new BranchResource($branch),
        ]);
    }

    /**
     * Update a branch.
     */
    public function update(UpdateBranchRequest $request, string $id): JsonResponse
    {
        /** @var Business $business */
        $business = $request->attributes->get('current_business');

        $isOwner = $request->attributes->get('is_business_owner', false)
            || $request->user()->isPlatformAdmin();

        if (! $isOwner) {
            return response()->json([
                'message' => 'Only the business owner can update branches.',
            ], 403);
        }

        $branch = $business->branches()->where('id', $id)->firstOrFail();
        $branch->update($request->validated());

        return response()->json([
            'message' => 'Branch updated successfully.',
            'data' => new BranchResource($branch->fresh()),
        ]);
    }

    /**
     * Soft-delete (deactivate) a branch. Cannot delete the last / head-office branch.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        /** @var Business $business */
        $business = $request->attributes->get('current_business');

        $isOwner = $request->attributes->get('is_business_owner', false)
            || $request->user()->isPlatformAdmin();

        if (! $isOwner) {
            return response()->json([
                'message' => 'Only the business owner can delete branches.',
            ], 403);
        }

        $branch = $business->branches()->where('id', $id)->firstOrFail();

        if ($branch->is_head_office) {
            return response()->json([
                'message' => 'Cannot delete the head-office branch.',
            ], 422);
        }

        $activeCount = $business->branches()->where('status', 'active')->count();
        if ($activeCount <= 1) {
            return response()->json([
                'message' => 'Cannot delete the only active branch.',
            ], 422);
        }

        $branch->update(['status' => 'inactive']);
        $branch->delete();

        return response()->json([
            'message' => 'Branch deactivated successfully.',
        ]);
    }
}
