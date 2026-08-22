<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\ReferralService;
use Illuminate\Console\Command;

class BackfillReferralCodes extends Command
{
    protected $signature = 'referrals:backfill-codes';

    protected $description = 'Generate unique referral codes for users who do not have one';

    public function handle(ReferralService $referralService): int
    {
        $updated = 0;

        User::query()
            ->whereNull('referral_code')
            ->orderBy('created_at')
            ->chunkById(100, function ($users) use ($referralService, &$updated) {
                foreach ($users as $user) {
                    $referralService->ensureUserHasCode($user);
                    $updated++;
                }
            }, 'id');

        $this->info("Referral codes generated for {$updated} user(s).");

        return self::SUCCESS;
    }
}
