<?php

namespace App\Modules\Payroll\Enums;

use App\Core\Enums\HasOptions;

/** What a salary head does to the payslip. */
enum HeadType: string
{
    use HasOptions;

    case Earning = 'earning';       // adds to the salary (Basic, House rent)
    case Deduction = 'deduction';   // taken from it (PF, Tax)
    case Info = 'info';             // shown on the payslip only (employer contribution)

    public function label(): string
    {
        return match ($this) {
            self::Earning => __('Earning'),
            self::Deduction => __('Deduction'),
            self::Info => __('Information only'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Earning => 'success',
            self::Deduction => 'danger',
            self::Info => 'neutral',
        };
    }
}
