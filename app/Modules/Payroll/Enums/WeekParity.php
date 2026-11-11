<?php

namespace App\Modules\Payroll\Enums;

use App\Core\Enums\HasOptions;

/**
 * For an alternating weekend day: on which occurrences of that weekday in the YEAR it is off.
 * Odd = the 1st, 3rd, 5th … Saturday of the year; Even = the 2nd, 4th, 6th …
 * Same numbers and counting as legacy (`typeOne` / `typeTwo`, Helper::getDatesByDay). NULL = every week.
 */
enum WeekParity: int
{
    use HasOptions;

    case Odd = 1;
    case Even = 2;

    public function label(): string
    {
        return match ($this) {
            self::Odd => __('Odd weeks (1st, 3rd, 5th …)'),
            self::Even => __('Even weeks (2nd, 4th, 6th …)'),
        };
    }

    /** Does the $n-th occurrence (1-based) of a weekday fall in this parity? */
    public function matches(int $n): bool
    {
        return $this === self::Odd ? $n % 2 === 1 : $n % 2 === 0;
    }
}
