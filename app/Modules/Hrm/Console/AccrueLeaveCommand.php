<?php

namespace App\Modules\Hrm\Console;

use App\Modules\Hrm\Services\AccrueEarnedLeave;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Credits earned leave for the current company (AccrueEarnedLeave). Safe to run again: credited periods are
 * skipped. Scheduled every night at 03:00 (after attendance processing, which PerDaysWorked needs). [LV-05]
 *
 *     php artisan tenants:run hrm:accrue-leave
 *     php artisan tenants:run "hrm:accrue-leave --date=2027-01-01 --employee=5"
 */
class AccrueLeaveCommand extends Command
{
    protected $signature = 'hrm:accrue-leave
                            {--date= : Act as if today were this date (YYYY-MM-DD); default today}
                            {--employee=* : Only these employee ids}';

    protected $description = 'Credit earned leave (entitlements and accruals) for the current company';

    public function handle(AccrueEarnedLeave $accrual): int
    {
        if (! tenant()) {
            $this->components->error('No company selected. Run it as: php artisan tenants:run hrm:accrue-leave');

            return self::FAILURE;
        }
        if (! tenant()->hasModule('hrm')) {
            return self::SUCCESS;
        }

        $ids = array_map('intval', (array) $this->option('employee')) ?: null;
        $done = $accrual->run($this->today(), $ids);
        $this->components->info("Credited {$done['periods']} periods, ".round($done['days'], 2).' days.');

        return self::SUCCESS;
    }

    private function today(): CarbonImmutable
    {
        return $this->option('date')
            ? CarbonImmutable::parse((string) $this->option('date'))
            : CarbonImmutable::now(settings('timezone', config('app.timezone')));
    }
}
