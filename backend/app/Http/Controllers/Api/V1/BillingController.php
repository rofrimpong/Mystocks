<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class BillingController extends Controller
{
    public function initialize(Request $request): JsonResponse
    {
        $data = $request->validate([
            'plan_slug' => ['required', 'string', 'in:starter,business,pro'],
            'billing_cycle' => ['required', 'string', 'in:monthly,yearly'],
        ]);

        $business = $request->attributes->get('current_business');
        if (! $business) {
            return response()->json(['message' => 'Business context required.'], 400);
        }
        $isOwner = (bool) $request->attributes->get('is_business_owner', false) || $request->user()->isPlatformAdmin();
        if (! $isOwner) {
            return response()->json(['message' => 'Only the business owner can change subscription plans.'], 403);
        }


        $plan = DB::table('plans')
            ->where('slug', $data['plan_slug'])
            ->where('is_active', true)
            ->first();

        if (! $plan) {
            return response()->json(['message' => 'Selected pricing plan is unavailable.'], 422);
        }

        $amount = $data['billing_cycle'] === 'yearly' ? (float) $plan->price_yearly : (float) $plan->price_monthly;
        if ($amount <= 0) {
            return response()->json(['message' => 'This plan does not require online checkout.'], 422);
        }

        $secret = (string) config('services.paystack.secret_key');
        if ($secret === '') {
            return response()->json(['message' => 'Online payments are not configured yet.'], 503);
        }

        $reference = 'CNMG-'.strtoupper(Str::random(20));
        $baseUrl = rtrim((string) config('services.paystack.payment_url'), '/');

        $response = Http::withToken($secret)->acceptJson()->post($baseUrl.'/transaction/initialize', [
            'email' => $request->user()->email,
            'amount' => (int) round($amount * 100),
            'currency' => 'GHS',
            'reference' => $reference,
            'callback_url' => config('services.paystack.callback_url'),
            'metadata' => [
                'business_id' => $business->id,
                'plan_slug' => $plan->slug,
                'billing_cycle' => $data['billing_cycle'],
            ],
        ]);

        if (! $response->successful() || ! $response->json('status')) {
            return response()->json(['message' => 'Could not initialize payment. Please try again.'], 502);
        }

        DB::table('platform_payments')->insert([
            'id' => (string) Str::uuid(),
            'business_id' => $business->id,
            'subscription_id' => null,
            'amount' => $amount,
            'currency' => 'GHS',
            'method' => 'other',
            'provider' => 'paystack',
            'provider_reference' => $reference,
            'status' => 'pending',
            'provider_response' => json_encode($response->json()),
            'paid_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'data' => [
                'authorization_url' => $response->json('data.authorization_url'),
                'reference' => $reference,
            ],
        ]);
    }

    public function verify(Request $request): JsonResponse
    {
        $data = $request->validate([
            'reference' => ['required', 'string', 'max:150'],
        ]);

        $business = $request->attributes->get('current_business');
        if (! $business) {
            return response()->json(['message' => 'Business context required.'], 400);
        }
        $isOwner = (bool) $request->attributes->get('is_business_owner', false) || $request->user()->isPlatformAdmin();
        if (! $isOwner) {
            return response()->json(['message' => 'Only the business owner can change subscription plans.'], 403);
        }


        $payment = DB::table('platform_payments')
            ->where('business_id', $business->id)
            ->where('provider', 'paystack')
            ->where('provider_reference', $data['reference'])
            ->first();

        if (! $payment) {
            return response()->json(['message' => 'Payment reference not found.'], 404);
        }

        if ($payment->status === 'successful') {
            return response()->json(['message' => 'Payment has already been verified.']);
        }

        $secret = (string) config('services.paystack.secret_key');
        $baseUrl = rtrim((string) config('services.paystack.payment_url'), '/');

        if ($secret === '') {
            return response()->json(['message' => 'Online payments are not configured yet.'], 503);
        }

        $response = Http::withToken($secret)
            ->acceptJson()
            ->get($baseUrl.'/transaction/verify/'.urlencode($data['reference']));

        if (! $response->successful() || ! $response->json('status')) {
            return response()->json(['message' => 'Could not verify payment with Paystack.'], 502);
        }

        if ($response->json('data.status') !== 'success') {
            return response()->json(['message' => 'Payment has not been completed successfully.'], 422);
        }

        $verifiedAmount = (int) $response->json('data.amount');
        $expectedAmount = (int) round(((float) $payment->amount) * 100);
        $currency = strtoupper((string) $response->json('data.currency'));

        if ($verifiedAmount !== $expectedAmount || $currency !== 'GHS') {
            return response()->json(['message' => 'Verified payment amount or currency does not match this checkout.'], 422);
        }

        $planSlug = (string) $response->json('data.metadata.plan_slug');
        $billingCycle = (string) $response->json('data.metadata.billing_cycle');
        $metadataBusinessId = (string) $response->json('data.metadata.business_id');

        if ($metadataBusinessId !== (string) $business->id || ! in_array($billingCycle, ['monthly', 'yearly'], true)) {
            return response()->json(['message' => 'Payment metadata is invalid.'], 422);
        }

        $plan = DB::table('plans')->where('slug', $planSlug)->where('is_active', true)->first();
        if (! $plan) {
            return response()->json(['message' => 'Paid pricing plan could not be resolved.'], 422);
        }

        $subscriptionId = (string) Str::uuid();
        $periodStart = now();
        $periodEnd = $billingCycle === 'yearly' ? now()->addYear() : now()->addMonth();

        DB::transaction(function () use ($business, $plan, $payment, $response, $billingCycle, $subscriptionId, $periodStart, $periodEnd) {
            DB::table('subscriptions')
                ->where('business_id', $business->id)
                ->whereIn('status', ['active', 'trialing'])
                ->update([
                    'status' => 'expired',
                    'ends_at' => now(),
                    'updated_at' => now(),
                ]);

            DB::table('subscriptions')->insert([
                'id' => $subscriptionId,
                'business_id' => $business->id,
                'plan_id' => $plan->id,
                'status' => 'active',
                'billing_cycle' => $billingCycle,
                'trial_ends_at' => null,
                'current_period_starts_at' => $periodStart,
                'current_period_ends_at' => $periodEnd,
                'cancelled_at' => null,
                'ends_at' => null,
                'metadata' => json_encode(['provider' => 'paystack']),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('platform_payments')->where('id', $payment->id)->update([
                'subscription_id' => $subscriptionId,
                'status' => 'successful',
                'provider_response' => json_encode($response->json()),
                'paid_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('businesses')->where('id', $business->id)->update([
                'plan' => $plan->slug,
                'status' => 'active',
                'trial_ends_at' => null,
                'updated_at' => now(),
            ]);
        });

        return response()->json([
            'message' => 'Payment verified and subscription activated successfully.',
            'data' => [
                'plan' => $plan->slug,
                'billing_cycle' => $billingCycle,
                'current_period_ends_at' => $periodEnd->toIso8601String(),
            ],
        ]);
    }
}
