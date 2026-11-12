<?php

namespace App\Modules\Hrm\Data;

use Carbon\CarbonImmutable;

/**
 * One earning period that is due (LeaveEarning): what to credit, when, to which leave year, and why. [LV-05]
 * $start is also the "already done?" key (leave_accruals.period_start).
 */
final readonly class EarnedPeriod
{
    public function __construct(
        public CarbonImmutable $start,
        public CarbonImmutable $end,
        public CarbonImmutable $creditOn,   // the ledger entry_date
        public int $year,                   // LeaveYear::$year
        public float $days,
        public string $basis,
    ) {}
}
