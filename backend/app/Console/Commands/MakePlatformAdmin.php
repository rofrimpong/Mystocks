<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class MakePlatformAdmin extends Command
{
    protected $signature = 'app:make-platform-admin {email}';
    protected $description = 'Grant CNMG STOCKS platform administrator access to an existing user';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();
        if (! $user) { $this->error('User not found.'); return self::FAILURE; }
        $user->update(['is_platform_admin' => true, 'is_active' => true]);
        $this->info($user->email.' is now a platform administrator.');
        return self::SUCCESS;
    }
}
