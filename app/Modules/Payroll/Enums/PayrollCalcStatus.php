<?php

namespace App\Modules\Payroll\Enums;

use App\Core\Enums\HasOptions;

/** Whether one employee of a run has been calculated (payroll_run_employees.calc_status). */
enum PayrollCalcStatus: string
{
    use HasOptions;

    case Pending = 'pending';   // not calculated yet
    case Ok = 'ok';             // items written (may still have warnings)
    case Failed = 'failed';     // the calculation threw: see `error`

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Pending'),
            self::Ok => __('Calculated'),
            self::Failed => __('Failed'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'neutral',
            self::Ok => 'success',
            self::Failed => 'danger',
        };
    }
}
