<?php

namespace App;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WorkerHealth
{
    public const LAST_RUN_KEY = 'scheduler.last_run_at';

    public function recordSchedulerRun(): void
    {
        Cache::forever(self::LAST_RUN_KEY, now()->utc()->toIso8601String());

        Log::info($this->summary());
    }

    public function lastSchedulerRunAt(): ?CarbonImmutable
    {
        $value = Cache::get(self::LAST_RUN_KEY);

        if (! is_string($value) || $value === '') {
            return null;
        }

        return CarbonImmutable::parse($value);
    }

    public function pendingJobs(): int
    {
        return (int) DB::table('jobs')->count();
    }

    public function summary(): string
    {
        $lastRun = $this->lastSchedulerRunAt();
        $pending = $this->pendingJobs();
        $schedule = $lastRun === null
            ? 'Schemaläggaren har inte körts.'
            : 'Schemaläggaren kördes '.$lastRun->timezone('Europe/Stockholm')->locale('sv')->translatedFormat('j F Y \k\l. H:i').'.';
        $jobs = match ($pending) {
            0 => 'Inga jobb väntar.',
            1 => '1 jobb väntar.',
            default => $pending.' jobb väntar.',
        };

        return $schedule.' '.$jobs;
    }
}
