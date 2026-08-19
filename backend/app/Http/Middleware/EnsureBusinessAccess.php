<?php

namespace App\Http\Middleware;

use App\Models\Business;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBusinessAccess
{
    /**
     * Ensure the authenticated user belongs to the requested business
     * and set the current business on the request for downstream use.
     *
     * Expects header: X-Business-Id (UUID)
     * Optional header: X-Branch-Id (UUID)
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Platform admins can access any business when explicitly provided
        if ($user->isPlatformAdmin()) {
            $businessId = $request->header('X-Business-Id') ?? $request->input('business_id');

            if ($businessId) {
                $business = Business::find($businessId);
                if (! $business) {
                    return response()->json(['message' => 'Business not found.'], 404);
                }
                $request->attributes->set('current_business', $business);
                $this->setBranchIfProvided($request, $business);
            }

            return $next($request);
        }

        $businessId = $request->header('X-Business-Id') ?? $request->input('business_id');

        if (! $businessId) {
            // Fallback: use the first active business the user belongs to
            $membership = $user->businesses()
                ->wherePivot('is_active', true)
                ->whereIn('businesses.status', ['active', 'trial'])
                ->first();

            if (! $membership) {
                return response()->json([
                    'message' => 'No active business found for this user. Provide X-Business-Id header.',
                ], 403);
            }

            $request->attributes->set('current_business', $membership);
            $request->attributes->set('current_branch_id', $membership->pivot->branch_id);

            return $next($request);
        }

        $membership = $user->businesses()
            ->where('businesses.id', $businessId)
            ->wherePivot('is_active', true)
            ->whereIn('businesses.status', ['active', 'trial'])
            ->first();

        if (! $membership) {
            return response()->json([
                'message' => 'You do not have access to this business.',
            ], 403);
        }

        $request->attributes->set('current_business', $membership);
        $request->attributes->set('is_business_owner', (bool) $membership->pivot->is_owner);
        $request->attributes->set('current_branch_id', $membership->pivot->branch_id);

        $this->setBranchIfProvided($request, $membership);

        return $next($request);
    }

    private function setBranchIfProvided(Request $request, Business $business): void
    {
        $branchId = $request->header('X-Branch-Id') ?? $request->input('branch_id');

        if ($branchId) {
            $branch = $business->branches()->where('id', $branchId)->where('status', 'active')->first();

            if (! $branch) {
                // Do not hard-fail; just ignore invalid branch header
                return;
            }

            $request->attributes->set('current_branch', $branch);
            $request->attributes->set('current_branch_id', $branch->id);
        }
    }
}
