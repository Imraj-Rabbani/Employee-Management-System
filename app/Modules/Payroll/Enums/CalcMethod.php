<?php

namespace App\Modules\Payroll\Enums;

use App\Core\Enums\HasOptions;

/** How a head's amount is worked out. [PC-01] */
enum CalcMethod: string
{
    use HasOptions;

    case Fixed = 'fixed';                    // the same amount for everyone (uses "amount")
    case PercentOfBasic = 'percent_basic';   // rate % of basic
    case PercentOfGross = 'percent_gross';   // rate % of gross
    case Formula = 'formula';                // an expression, evaluated by the payroll run (Phase 10)
    case Attendance = 'attendance';          // "amount" per payable day

    public function label(): string
    {
        return match ($this) {
            self::Fixed => __('Fixed amount'),
            self::PercentOfBasic => __('% of basic'),
            self::PercentOfGross => __('% of gross'),
            self::Formula => __('Formula'),
            self::Attendance => __('Per attendance day'),
        };
    }

    public function usesAmount(): bool
    {
        return $this === self::Fixed || $this === self::Attendance;
    }

    public function usesRate(): bool
    {
        return $this === self::PercentOfBasic || $this === self::PercentOfGross;
    }
}
