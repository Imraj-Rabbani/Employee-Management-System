<?php

namespace App\Modules\Payroll\Enums;

use App\Core\Enums\HasOptions;

/** Why a run pays back pay for an earlier period (payroll_arrears.reason). */
enum ArrearReason: string
{
    use HasOptions;

    case SalaryChange = 'salary_change';   // a salary saved after that period was paid, valid in it (back-dated)
    case LateJoiner = 'late_joiner';       // joined after the threshold day: that period was not paid at all

    public function label(): string
    {
        return match ($this) {
            self::SalaryChange => __('Back-dated salary change'),
            self::LateJoiner => __('Late joiner'),
        };
    }
}
