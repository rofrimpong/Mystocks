<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Business\UpdateBusinessRequest;
use App\Http\Resources\BusinessResource;
use App\Models\Business;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BusinessController extends Controller
{
    /**
     * List businesses the authenticated user belongs to.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $businesses = $user->businesses()
            ->wherePivot('is_active', true)
            ->with(['branches' => fn ($q) => $q->where('status', 'active')])
            ->get();

        return response()->json([
            'data' => BusinessResource::collection($businesses),
        ]);
    }

    /**
     * Show a single business (must be accessible by the user).
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $business = $this->findAccessibleBusiness($request, $id);

        $business->load(['branches' => fn ($q) => $q->where('status', 'active')]);

        return response()->json([
            'data' => new BusinessResource($business),
        ]);
    }

    /**
     * Update business details (owner only for now).
     */
    public function update(UpdateBusinessRequest $request, string $id): JsonResponse
    {
        $business = $this->findAccessibleBusiness($request, $id);

        $isOwner = $request->user()->businesses()
            ->where('businesses.id', $business->id)
            ->wherePivot('is_owner', true)
            ->exists();

        if (! $isOwner && ! $request->user()->isPlatformAdmin()) {
            return response()->json([
                'message' => 'Only the business owner can update business settings.',
            ], 403);
        }

        $business->update($request->validated());

        return response()->json([
            'message' => 'Business updated successfully.',
            'data' => new BusinessResource($business->fresh()),
        ]);
    }

    /**
     * Get current business context (from middleware).
     */
    public function current(Request $request): JsonResponse
    {
        /** @var Business|null $business */
        $business = $request->attributes->get('current_business');

        if (! $business) {
            return response()->json([
                'message' => 'No business context. Provide X-Business-Id header.',
            ], 400);
        }

        $business->load(['branches' => fn ($q) => $q->where('status', 'active')]);

        return response()->json([
            'data' => new BusinessResource($business),
            'meta' => [
                'is_owner' => (bool) $request->attributes->get('is_business_owner', false),
                'current_branch_id' => $request->attributes->get('current_branch_id'),
            ],
        ]);
    }

    private function findAccessibleBusiness(Request $request, string $id): Business
    {
        $user = $request->user();

        if ($user->isPlatformAdmin()) {
            $business = Business::find($id);
            if (! $business) {
                abort(404, 'Business not found.');
            }
            return $business;
        }

        $business = $user->businesses()
            ->where('businesses.id', $id)
            ->wherePivot('is_active', true)
            ->first();

        if (! $business) {
            abort(403, 'You do not have access to this business.');
        }

        return $business;
    }
}
