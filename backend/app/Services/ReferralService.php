<?php

namespace App\Services;

use App\Models\Referral;
use App\Models\User;
use Illuminate\Support\Str;
use RuntimeException;

class ReferralService
{
    public function generateUniqueCode(): string
    {
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $code = 'CNMG-'.Str::upper(Str::random(6));

            if (! User::where('referral_code', $code)->exists()) {
                return $code;
            }
        }

        throw new RuntimeException('Unable to generate a unique referral code.');
    }

    public function ensureUserHasCode(User $user): string
    {
        if ($user->referral_code) {
            return $user->referral_code;
        }

        $user->forceFill([
            'referral_code' => $this->generateUniqueCode(),
        ])->save();

        return $user->referral_code;
    }

    public function findReferrer(?string $code): ?User
    {
        $code = Str::upper(trim((string) $code));

        if ($code === '') {
            return null;
        }

        return User::where('referral_code', $code)->first();
    }

    public function createReferral(User $referrer, User $referredUser): Referral
    {
        if ($referrer->is($referredUser)) {
            throw new RuntimeException('A user cannot refer themselves.');
        }

        return Referral::create([
            'referrer_id' => $referrer->id,
            'referred_user_id' => $referredUser->id,
            'referral_code' => $referrer->referral_code,
            'status' => Referral::STATUS_REGISTERED,
        ]);
    }
}
