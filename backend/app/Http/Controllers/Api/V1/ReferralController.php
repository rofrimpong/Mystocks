<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Referral;
use App\Services\ReferralService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReferralController extends Controller
{
    public function summary(
        Request $request,
        ReferralService $referralService
    ): JsonResponse {
        $user = $request->user();

        $code = $referralService->ensureUserHasCode($user);

        $referrals = Referral::query()
            ->where('referrer_id', $user->id);

        $total = (clone $referrals)->count();

        $qualified = (clone $referrals)
            ->whereIn('status', [
                Referral::STATUS_QUALIFIED,
                Referral::STATUS_REWARDED,
            ])
            ->count();

        $rewarded = (clone $referrals)
            ->where('status', Referral::STATUS_REWARDED)
            ->count();

        $recent = (clone $referrals)
            ->with('referredUser:id,name,created_at')
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (Referral $referral) => [
                'id' => $referral->id,
                'name' => $referral->referredUser?->name,
                'status' => $referral->status,
                'joined_at' => $referral->created_at?->toIso8601String(),
                'qualified_at' => $referral->qualified_at?->toIso8601String(),
                'rewarded_at' => $referral->rewarded_at?->toIso8601String(),
            ]);

        return response()->json([
            'data' => [
                'referral_code' => $code,
                'total_referrals' => $total,
                'qualified_referrals' => $qualified,
                'rewarded_referrals' => $rewarded,
                'recent_referrals' => $recent,
            ],
        ]);
    }
}
