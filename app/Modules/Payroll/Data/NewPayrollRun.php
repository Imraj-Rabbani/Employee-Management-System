<?php

namespace App\Modules\Payroll\Data;

use App\Core\Support\Period;
use App\Modules\Payroll\Enums\PayrollRunType;
use Carbon\CarbonImmutable;

/**
 * What the "New payroll run" form asks for, handed to CreatePayrollRun.
 *
 *   entityId        NULL = the whole company
 *   salaryHeadId    head-wise runs only
 *   employeeCodes   [] = everyone in the scope; otherwise only these employees (e.g. an off-cycle payment for three people)
 */
final readonly class NewPayrollRun
{
    /** @param  list<string>  $employeeCodes */
    public function __construct(
        public Period $period,
        public PayrollRunType $type = PayrollRunType::Regular,
        public ?int $entityId = null,
        public ?int $salaryHeadId = null,
        public array $employeeCodes = [],
        public ?CarbonImmutable $payDate = null,
        public ?string $note = null,
    ) {}
}
