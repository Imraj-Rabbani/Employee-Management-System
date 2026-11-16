<?php

namespace App\Modules\Payroll\Data;

use App\Core\Support\Money;
use App\Modules\Payroll\Enums\HeadType;
use App\Modules\Payroll\Enums\ProfileValueType;
use App\Modules\Payroll\Models\SalaryHead;

/**
 * One head of a salary: the head, how it was worked out ("50% of basic") and the amount.
 * valueType / rate are kept when the amount came from a percent (saved with an employee's salary); NULL = the basic
 * or a plain amount.
 */
final readonly class SalaryLine
{
    public function __construct(
        public SalaryHead $head,
        public string $basis,
        public Money $amount,
        public ?ProfileValueType $valueType = null,
        public ?string $rate = null,
    ) {}

    /** Earnings counted in gross (the basic always is). */
    public function inGross(): bool
    {
        return $this->head->type === HeadType::Earning && $this->head->in_gross;
    }
}
