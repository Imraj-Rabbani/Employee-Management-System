<?php

namespace App\Modules\Payroll\Data;

use App\Core\Support\Money;
use App\Modules\Payroll\Enums\PayrollItemSource;
use App\Modules\Payroll\Models\SalaryHead;

/**
 * One head of one employee in a run, before it is saved (it becomes a PayrollItem in the Persist stage).
 *   amount   what is paid in this period (after proration)
 *   actual   what the full period would have been
 *   arrear   back pay included in amount (10.7)
 */
final readonly class PayrollLine
{
    public function __construct(
        public SalaryHead $head,
        public PayrollItemSource $source,
        public Money $amount,
        public Money $actual,
        public Money $arrear,
    ) {}
}
