<?php

namespace App\Core\Support;

use App\Core\Enums\HasOptions;

/** Which part of the month a payroll period covers. */
enum PeriodCycle: string
{
    use HasOptions;

    case Monthly = 'monthly';
    case FirstHalf = 'first_half';
    case SecondHalf = 'second_half';

    public function label(): string
    {
        return match ($this) {
            self::Monthly => 'Monthly',
            self::FirstHalf => '1st half',
            self::SecondHalf => '2nd half',
        };
    }
}
