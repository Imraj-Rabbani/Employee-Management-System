<?php

namespace App\Modules\Payroll\Data;

use App\Core\Support\Money;
use App\Modules\Payroll\Enums\HeadType;

/**
 * A salary: every head with its amount + totals. Made by SalaryProfileCalculator (a profile applied) or from an
 * employee's saved rows (EmployeeSalary::breakdown()). [PC-02, PC-03]
 * gross = the earnings counted in gross (basic included); net = gross − deductions. Info heads and earnings
 * outside gross are listed, but in neither total.
 */
final readonly class SalaryBreakdown
{
    /** @param  list<SalaryLine>  $lines  in head order (sequence, name) */
    private function __construct(
        public array $lines,
        public Money $basic,
        public Money $gross,
        public Money $deductions,
        public Money $net,
    ) {}

    /** @param  list<SalaryLine>  $lines  any order; the basic is the line whose head is_basic */
    public static function fromLines(array $lines): self
    {
        usort($lines, fn (SalaryLine $a, SalaryLine $b) => [$a->head->sequence, $a->head->name] <=> [$b->head->sequence, $b->head->name]);

        $basic = $gross = $deductions = Money::zero();
        foreach ($lines as $line) {
            if ($line->head->is_basic) {
                $basic = $basic->add($line->amount);
            }
            if ($line->inGross()) {
                $gross = $gross->add($line->amount);
            }
            if ($line->head->type === HeadType::Deduction) {
                $deductions = $deductions->add($line->amount);
            }
        }

        return new self($lines, $basic, $gross, $deductions, $gross->subtract($deductions));
    }

    /** @return array<int, Money> salary_head_id => amount */
    public function amounts(): array
    {
        $amounts = [];
        foreach ($this->lines as $line) {
            $amounts[$line->head->id] = $line->amount;
        }

        return $amounts;
    }

    public function isEmpty(): bool
    {
        return $this->lines === [];
    }
}
