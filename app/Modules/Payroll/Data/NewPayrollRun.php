<?php

namespace App\Modules\Payroll\Data;

use App\Core\Support\Period;
use App\Modules\Payroll\Enums\AmountBasis;
use App\Modules\Payroll\Enums\PayrollRunType;
use Carbon\CarbonImmutable;

/**
 * What the "New payroll run" form asks for, handed to CreatePayrollRun.
 *
 *   entityId        NULL = the whole company
 *   salaryHeadId    head-wise runs only
 *   employeeCodes   [] = everyone in the scope; otherwise only these employees (not for upload runs: the file says who)
 *   amountBasis     head-wise: head rule / salary / upload; off-cycle: always upload; regular: null
 *   religions, confirmedOnly, joinedBefore, prorateByService   head-wise eligibility (10.11)
 */
final readonly class NewPayrollRun
{
    /**
     * @param  list<string>  $employeeCodes
     * @param  list<int>  $religions
     */
    public function __construct(
        public Period $period,
        public PayrollRunType $type = PayrollRunType::Regular,
        public ?int $entityId = null,
        public ?int $salaryHeadId = null,
        public array $employeeCodes = [],
        public ?CarbonImmutable $payDate = null,
        public ?string $note = null,
        public ?AmountBasis $amountBasis = null,
        /** @var list<int> Religion values, [] = any */
        public array $religions = [],
        public bool $confirmedOnly = false,
        public ?CarbonImmutable $joinedBefore = null,
        public bool $prorateByService = false,
    ) {}

    /** What the run really uses: regular → null, off-cycle → upload, head-wise → the chosen basis (default head rule). */
    public function basis(): ?AmountBasis
    {
        return match ($this->type) {
            PayrollRunType::Regular, PayrollRunType::FinalSettlement => null,
            PayrollRunType::OffCycle => AmountBasis::Upload,
            PayrollRunType::HeadWise => $this->amountBasis ?? AmountBasis::HeadRule,
        };
    }

    /** @return array{religions: list<int>, confirmed_only: bool, joined_before: ?CarbonImmutable} */
    public function eligibility(): array
    {
        $headWise = $this->type === PayrollRunType::HeadWise;

        return [
            'religions' => $headWise ? array_map('intval', $this->religions) : [],
            'confirmed_only' => $headWise && $this->confirmedOnly,
            'joined_before' => $headWise ? $this->joinedBefore : null,
        ];
    }
}
