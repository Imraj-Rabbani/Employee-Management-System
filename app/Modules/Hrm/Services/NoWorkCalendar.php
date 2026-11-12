<?php

namespace App\Modules\Hrm\Services;

use App\Modules\Hrm\Contracts\WorkCalendar;
use App\Modules\Hrm\Models\Employee;
use Carbon\CarbonImmutable;

/** Fallback when nothing else is bound: no days off, every day of a leave counts. */
class NoWorkCalendar implements WorkCalendar
{
    public function daysOff(Employee $employee, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return [];
    }
}
