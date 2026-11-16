<?php

namespace App\Modules\Payroll\Data;

use App\Core\Support\Money;
use App\Modules\Payroll\Enums\HeadType;

/**
 * A salary profile applied to a basic or gross (SalaryProfileCalculator): every head with its amount + totals. [PC-02]
 * gross = basic + the earnings counted in gross; net = gross − deductions (info heads and earnings outside gross
 * are listed, but in neither total).
 */
final readonly class SalaryBreakdown
{
    /** @param  list<SalaryLine>  $lines  in head order (sequence, name), basic included */
    public function __construct(
        public array $lines,
        public Money $basic,
        public Money $gross,
        public Money $deductions,
        public Money $net,
    ) {}

    /** @return array<int, Money> salary_head_id => amount, e.g. to save as the employee's salary (9.4) */
    public function amounts(): array
    {
        $amounts = [];
        foreach ($this->lines as $line) {
            $amounts[$line->head->id] = $line->amount;
        }

        return $amounts;
    }

    /** @return list<SalaryLine> */
    public function linesOfType(HeadType $type): array
    {
        return array_values(array_filter($this->lines, fn (SalaryLine $l) => $l->head->type === $type));
    }
}
