<?php

namespace App\Modules\Hrm\Enums;

use App\Core\Enums\HasOptions;

/**
 * How an employee gets the days of a leave type in their profile (legacy `leavetypes.earnType`). [LV-01, LV-05]
 * Same numbers as legacy so migrated data keeps its meaning. Legacy 3 ("earn each after threshold") and
 * 4 ("full month only") were not used: not ported.
 *
 * The days themselves come from the profile (leave_profile_items.days_per_year). Credited by 8.2 / 8.5.
 */
enum LeaveEarnType: int
{
    use HasOptions;

    case Fixed = 0;
    case Prorata = 1;
    case PerDaysWorked = 2;
    case Anniversary = 5;
    case MonthlyAccrual = 6;

    public function label(): string
    {
        return match ($this) {
            self::Fixed => __('All days at the start of the year'),
            self::Prorata => __('Pro rata to the days employed in the year'),
            self::PerDaysWorked => __('1 day per N days worked'),
            self::Anniversary => __('All days on each work anniversary'),
            self::MonthlyAccrual => __('A share every month'),
        };
    }
}
