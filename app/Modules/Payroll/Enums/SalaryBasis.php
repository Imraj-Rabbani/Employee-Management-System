<?php

namespace App\Modules\Payroll\Enums;

use App\Core\Enums\HasOptions;

/** Which amount is given when a salary profile is applied: the basic, or the gross it must add up to. */
enum SalaryBasis: string
{
    use HasOptions;

    case Basic = 'basic';
    case Gross = 'gross';

    public function label(): string
    {
        return match ($this) {
            self::Basic => __('From basic'),
            self::Gross => __('From gross'),
        };
    }
}
