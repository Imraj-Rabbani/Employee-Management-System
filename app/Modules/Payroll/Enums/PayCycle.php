<?php

namespace App\Modules\Payroll\Enums;

use App\Core\Enums\HasOptions;

/** How often a head is paid. (Not the same as App\Core\Support\PeriodCycle, which is the part of the month.) [PC-01] */
enum PayCycle: string
{
    use HasOptions;

    case Monthly = 'monthly';
    case Annual = 'annual';        // e.g. yearly bonus: paid in a run of its own month
    case OneTime = 'one_time';     // paid once, e.g. joining bonus

    public function label(): string
    {
        return match ($this) {
            self::Monthly => __('Monthly'),
            self::Annual => __('Annual'),
            self::OneTime => __('One time'),
        };
    }
}
