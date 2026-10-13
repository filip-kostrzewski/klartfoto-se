<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('app:purge-expired-early-access-signups')]
#[Description('Remove early-access signups older than 12 months')]
class PurgeExpiredEarlyAccessSignups extends Command
{
    public function handle(): int
    {
        $deleted = DB::table('early_access_signups')
            ->where('created_at', '<=', now()->subMonths(12))
            ->delete();

        $this->info("Removed {$deleted} expired signup(s).");

        return self::SUCCESS;
    }
}
