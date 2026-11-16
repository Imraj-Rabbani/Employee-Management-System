<?php

namespace App\Modules\Payroll\Data;

use App\Core\Support\Money;
use App\Modules\Payroll\Models\SalaryHead;

/** One head of a worked-out salary: the head, how it was worked out ("50% of basic") and the amount. [PC-02] */
final readonly class SalaryLine
{
    public function __construct(
        public SalaryHead $head,
        public string $basis,
        public Money $amount,
        public bool $inGross,
    ) {}
}
