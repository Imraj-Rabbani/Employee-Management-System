<?php

namespace App\Modules\Payroll\Data;

use App\Core\Support\Money;
use App\Modules\Payroll\Enums\ArrearReason;
use App\Modules\Payroll\Models\PayrollRunEmployee;
use App\Modules\Payroll\Models\SalaryHead;

/** Back pay for one head of one earlier period (becomes a PayrollArrear in the Persist stage). */
final readonly class ArrearLine
{
    public function __construct(
        public SalaryHead $head,
        public PayrollRunEmployee $forRow,
        public ArrearReason $reason,
        public Money $due,
        public Money $paidBefore,
        public Money $amount,
    ) {}
}
