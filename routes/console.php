<?php

use App\WorkerHealth;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function (): void {
    app(WorkerHealth::class)->recordSchedulerRun();
})->everyMinute()->name('scheduler-heartbeat');

Schedule::command('app:purge-expired-early-access-signups')->daily();
Schedule::command('app:prune-spam-submissions')->daily();
Schedule::command('billing:trial-reminders')->dailyAt('07:30')->timezone('Europe/Stockholm');
