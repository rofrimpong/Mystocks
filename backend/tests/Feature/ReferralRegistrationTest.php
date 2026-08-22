<?php

namespace Tests\Feature;

use App\Models\Referral;
use App\Models\User;
use App\Services\ReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferralRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_without_referral_code_still_works(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Normal Customer',
            'email' => 'normal@example.com',
            'phone' => '0200000101',
            'password' => 'CnmgStockTest#9Xv7Qp2L!',
            'password_confirmation' => 'CnmgStockTest#9Xv7Qp2L!',
            'business_name' => 'Normal Business',
            'device_name' => 'test',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.user.email', 'normal@example.com');

        $user = User::where('email', 'normal@example.com')->firstOrFail();

        $this->assertNotNull($user->referral_code);
        $this->assertStringStartsWith('CNMG-', $user->referral_code);
        $this->assertDatabaseCount('referrals', 0);
    }

    public function test_valid_referral_code_records_referral_during_registration(): void
    {
        $referrer = User::create([
            'name' => 'Existing Referrer',
            'email' => 'referrer@example.com',
            'phone' => '0200000102',
            'password' => 'CnmgStockTest#9Xv7Qp2L!',
        ]);

        app(ReferralService::class)->ensureUserHasCode($referrer);

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Referred Customer',
            'email' => 'referred@example.com',
            'phone' => '0200000103',
            'password' => 'CnmgStockTest#9Xv7Qp2L!',
            'password_confirmation' => 'CnmgStockTest#9Xv7Qp2L!',
            'business_name' => 'Referred Business',
            'referral_code' => strtolower($referrer->referral_code),
            'device_name' => 'test',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.user.email', 'referred@example.com');

        $referredUser = User::where('email', 'referred@example.com')->firstOrFail();

        $this->assertNotNull($referredUser->referral_code);
        $this->assertNotSame($referrer->referral_code, $referredUser->referral_code);

        $this->assertDatabaseHas('referrals', [
            'referrer_id' => $referrer->id,
            'referred_user_id' => $referredUser->id,
            'referral_code' => $referrer->referral_code,
            'status' => Referral::STATUS_REGISTERED,
        ]);
    }

    public function test_invalid_referral_code_is_rejected(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Invalid Referral Customer',
            'email' => 'invalid-referral@example.com',
            'phone' => '0200000104',
            'password' => 'CnmgStockTest#9Xv7Qp2L!',
            'password_confirmation' => 'CnmgStockTest#9Xv7Qp2L!',
            'business_name' => 'Invalid Referral Business',
            'referral_code' => 'CNMG-NOTREAL',
            'device_name' => 'test',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('referral_code');

        $this->assertDatabaseMissing('users', [
            'email' => 'invalid-referral@example.com',
        ]);
    }

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

        $response = $this
            ->actingAs($referrer, 'sanctum')
            ->getJson('/api/v1/referrals/summary');

        $response
            ->assertOk()
            ->assertJsonPath('data.referral_code', $referrer->referral_code)
            ->assertJsonPath('data.total_referrals', 1)
            ->assertJsonPath('data.qualified_referrals', 0)
            ->assertJsonPath('data.rewarded_referrals', 0)
            ->assertJsonPath('data.recent_referrals.0.name', 'Summary Referral')
            ->assertJsonPath('data.recent_referrals.0.status', 'registered');
    }
}
