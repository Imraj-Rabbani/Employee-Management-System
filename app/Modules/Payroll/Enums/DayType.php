<?php

namespace App\Modules\Payroll\Enums;

use App\Core\Enums\HasOptions;

/**
 * What kind of day it was for the employee (attendance_days.day_type). Decides the overtime rate in
 * payroll: work on a weekend / holiday / festival is all overtime, paid at that day type's rate.
 * Replaces legacy's ot buckets (otRegular, otWeekend, otHoliday, otFestival).
 */
enum DayType: int
{
    use HasOptions;

    case Workday = 1;
    case Weekend = 2;    // weekend profile, or a day off in the roster
    case Holiday = 3;
    case Festival = 4;   // a holiday marked "festival"

    public function label(): string
    {
        return match ($this) {
            self::Workday => __('Workday'),
            self::Weekend => __('Weekend'),
            self::Holiday => __('Holiday'),
            self::Festival => __('Festival'),
        };
    }
}
