<?php

namespace Tests\Feature;

use App\Models\Referral;
use App\Models\User;
use App\Services\ReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferralSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_get_referral_summary(): void
    {
        $referrer = User::create([
            'name' => 'Summary Referrer',
            'email' => 'summary-referrer@example.com',
            'phone' => '0200000105',
            'password' => 'CnmgStockTest#9Xv7Qp2L!',
        ]);

        $referred = User::create([
            'name' => 'Summary Referral',
            'email' => 'summary-referral@example.com',
            'phone' => '0200000106',
            'password' => 'CnmgStockTest#9Xv7Qp2L!',
        ]);

        $service = app(ReferralService::class);

        $service->ensureUserHasCode($referrer);
        $service->ensureUserHasCode($referred);
        $service->createReferral($referrer, $referred);

        $referrer->refresh();

        $response = $this
            ->actingAs($referrer, 'sanctum')
            ->getJson('/api/v1/referrals/summary');

        $response
            ->assertOk()
            ->assertJsonPath('data.referral_code', $referrer->referral_code)
            ->assertJsonPath('data.total_referrals', 1)
            ->assertJsonPath('data.qualified_referrals', 0)
            ->assertJsonPath('data.rewarded_referrals', 0)
            ->assertJsonPath(
                'data.recent_referrals.0.name',
                'Summary Referral'
            )
            ->assertJsonPath(
                'data.recent_referrals.0.status',
                Referral::STATUS_REGISTERED
            );
    }

    public function test_referral_summary_requires_authentication(): void
    {
        $this->getJson('/api/v1/referrals/summary')
            ->assertUnauthorized();
    }
}
