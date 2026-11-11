<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Attendance devices (7.3d): pull punches from every company's devices. withoutOverlapping: a slow run
// (devices that time out) is not started a second time. Needs the scheduler: `php artisan schedule:work`
// locally, or a cron entry calling `php artisan schedule:run` every minute on the server.
Schedule::command('tenants:run payroll:sync-devices')->everyFiveMinutes()->withoutOverlapping()->runInBackground();

// Attendance processing (7.4): every night, yesterday's attendance for every company (queued, one job per
// employee: needs a queue worker, `php artisan queue:work`). 01:00 server time; "yesterday" is in each
// company's own timezone.
Schedule::command('tenants:run payroll:process-attendance')->dailyAt('01:00')->withoutOverlapping();
