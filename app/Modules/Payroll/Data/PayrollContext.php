<?php

namespace App\Modules\Payroll\Data;

use App\Core\Support\Period;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Payroll\Models\EmployeeSalary;
use App\Modules\Payroll\Models\PayrollRun;
use App\Modules\Payroll\Models\PayrollRunEmployee;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Everything the calculation of ONE employee in ONE run knows. The pipeline hands the same object from stage to
 * stage; each stage reads what earlier stages filled in and fills in its own part (Architecture §9.2):
 *
 *   ResolvePeriod         period, periodDays, cycleShare, workFrom, workTo
 *   LoadSalaryStructure   salary, salaryFrom
 *   ComputePayableDays    payableDays, lwpDays, absentDays
 *   CalculateEarnings     lines
 *   Persist               writes lines + totals to the database
 *
 * Any stage can add a note for the reviewer with warn(); the notes are saved with the employee's row.
 */
final class PayrollContext
{
    public Period $period;

    /** Calendar days of the period. */
    public int $periodDays = 0;

    /** Share of the MONTHLY salary this period pays, as a percent string: '100' for a month, '50' for a half-month cycle. */
    public string $cycleShare = '100';

    /** First and last day the employee works in the period (joined / left inside it narrows the period). */
    public CarbonImmutable $workFrom;

    public CarbonImmutable $workTo;

    /** @var Collection<int, EmployeeSalary> the employee's salary rows (heads loaded) valid on workTo */
    public Collection $salary;

    public ?CarbonImmutable $salaryFrom = null;

    public float $payableDays = 0.0;

    public float $lwpDays = 0.0;

    public float $absentDays = 0.0;

    /** @var list<PayrollLine> */
    public array $lines = [];

    /** @var list<string> */
    public array $warnings = [];

    public function __construct(
        public readonly PayrollRun $run,
        public readonly PayrollRunEmployee $row,
        public readonly Employee $employee,
    ) {
        $this->salary = new Collection;
    }

    public function warn(string $message): void
    {
        $this->warnings[] = $message;
    }

    /** Days as the "15.50" decimal string Money::multiply() wants. */
    public static function days(float $days): string
    {
        return number_format($days, 2, '.', '');
    }
}
