<?php

namespace App\Modules\Payroll\Enums;

use App\Core\Enums\HasOptions;

/** Which pipeline stage made a payroll item (payroll_items.source). Shown in the per-employee breakdown. */
enum PayrollItemSource: string
{
    use HasOptions;

    case Structure = 'structure';     // the employee's salary (employee_salaries)
    case Overtime = 'overtime';
    case Incentive = 'incentive';
    case Adjustment = 'adjustment';   // payroll_adjustments
    case Arrear = 'arrear';           // back-dated salary changes
    case Loan = 'loan';               // loan installment
    case Tax = 'tax';
    case Upload = 'upload';           // amounts from a CSV / Excel (off-cycle runs)
    case Plugin = 'plugin';           // a company's own payroll stage

    public function label(): string
    {
        return match ($this) {
            self::Structure => __('Salary'),
            self::Overtime => __('Overtime'),
            self::Incentive => __('Incentive'),
            self::Adjustment => __('Adjustment'),
            self::Arrear => __('Arrear'),
            self::Loan => __('Loan'),
            self::Tax => __('Tax'),
            self::Upload => __('Uploaded'),
            self::Plugin => __('Custom'),
        };
    }
}
