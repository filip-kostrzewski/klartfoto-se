<?php

namespace App\Console\Commands;

use App\Models\SpamSubmission;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:prune-spam-submissions')]
#[Description('Delete spam submissions older than 90 days')]
class PruneSpamSubmissions extends Command
{
    public function handle(): int
    {
        $deleted = SpamSubmission::query()
            ->where('created_at', '<', now()->subDays(90))
            ->delete();

        $this->info("Removed {$deleted} spam submission(s).");

        return self::SUCCESS;
    }
}
