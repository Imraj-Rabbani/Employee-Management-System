<?php

namespace App\Modules\Payroll\Enums;

use App\Core\Enums\HasOptions;

/**
 * Day of the week. Same numbers as legacy (`getDaysArray()`) AND as Carbon's `dayOfWeek`
 * (0 = Sunday … 6 = Saturday), so `Weekday::from($date->dayOfWeek)` just works.
 * Legacy -1 = "None" is not a case: store NULL instead.
 */
enum Weekday: int
{
    use HasOptions;

    case Sunday = 0;
    case Monday = 1;
    case Tuesday = 2;
    case Wednesday = 3;
    case Thursday = 4;
    case Friday = 5;
    case Saturday = 6;

    public function label(): string
    {
        return match ($this) {
            self::Sunday => __('Sunday'),
            self::Monday => __('Monday'),
            self::Tuesday => __('Tuesday'),
            self::Wednesday => __('Wednesday'),
            self::Thursday => __('Thursday'),
            self::Friday => __('Friday'),
            self::Saturday => __('Saturday'),
        };
    }
}
