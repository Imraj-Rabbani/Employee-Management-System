<?php

namespace App\Modules\Hrm\Console;

use App\Modules\Hrm\Services\CarryForwardLeave;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Leave year end for the current company (CarryForwardLeave): closes the previous leave year once it is
 * CLOSE_AFTER_DAYS days over, then expires carried days that ran out. Safe to run again. Scheduled every
 * night at 03:30, after hrm:accrue-leave (so the last month is earned before the year closes). [LV-05]
 *
 *     php artisan tenants:run hrm:carry-forward-leave
 *     php artisan tenants:run "hrm:carry-forward-leave --date=2027-01-20"
 */
class CarryForwardLeaveCommand extends Command
{
    protected $signature = 'hrm:carry-forward-leave
                            {--date= : Act as if today were this date (YYYY-MM-DD); default today}';

    protected $description = 'Close the previous leave year (carry forward / expire) and expire old carried days';

    public function handle(CarryForwardLeave $yearEnd): int
    {
        if (! tenant()) {
            $this->components->error('No company selected. Run it as: php artisan tenants:run hrm:carry-forward-leave');

            return self::FAILURE;
        }
        if (! tenant()->hasModule('hrm')) {
            return self::SUCCESS;
        }

        $today = $this->option('date')
            ? CarbonImmutable::parse((string) $this->option('date'))
            : CarbonImmutable::now(settings('timezone', config('app.timezone')));
        $r = $yearEnd->run($today);
        $this->components->info("Closed {$r['closed']} balances ({$r['waiting']} waiting for an approval), carried ".round($r['carried'], 2)." days, expired {$r['expired']} carried rows.");

        return self::SUCCESS;
    }
}
