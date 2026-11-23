<?php

namespace App\Modules\Payroll\Actions;

use App\Core\Support\Money;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Payroll\Data\PayrollContext;
use App\Modules\Payroll\Enums\PayrollCalcStatus;
use App\Modules\Payroll\Enums\PayrollRunStatus;
use App\Modules\Payroll\Enums\PayrollRunType;
use App\Modules\Payroll\Exceptions\PayrollException;
use App\Modules\Payroll\Models\PayrollAdjustment;
use App\Modules\Payroll\Models\PayrollArrear;
use App\Modules\Payroll\Models\PayrollItem;
use App\Modules\Payroll\Models\PayrollRunEmployee;
use App\Modules\Payroll\Services\PayrollRunScope;
use App\Modules\Payroll\Stages\Adjustments;
use App\Modules\Payroll\Stages\Arrears;
use App\Modules\Payroll\Stages\CalculateEarnings;
use App\Modules\Payroll\Stages\ComputePayableDays;
use App\Modules\Payroll\Stages\HeadWiseAmount;
use App\Modules\Payroll\Stages\LoadSalaryStructure;
use App\Modules\Payroll\Stages\Overtime;
use App\Modules\Payroll\Stages\Persist;
use App\Modules\Payroll\Stages\ResolvePeriod;
use App\Modules\Payroll\Stages\UploadedAmounts;
use Illuminate\Pipeline\Pipeline;
use LogicException;

/**
 * Calculates ONE employee of a payroll run: builds a PayrollContext and sends it through the stages (Architecture §9.2).
 * 10.4 queues one of these per employee; calling it again recalculates (10.9), replacing the old items.
 *
 *     app(CalculateEmployeePayroll::class)->handle($runEmployee)    the same row, now Ok or Failed
 *
 * A PayrollException from a stage (no salary, not employed ...) does not stop anything: the row is marked Failed with
 * the message, its old items are removed, and the run goes on with the others. Other exceptions are bugs and bubble up.
 */
class CalculateEmployeePayroll
{
    /**
     * In order. The other stages of Architecture §9.2 (incentives, arrears, loans, tax, a company's own stages) are
     * added before Persist by the next tasks.
     *
     * @var list<class-string>
     */
    public const STAGES = [
        ResolvePeriod::class,
        LoadSalaryStructure::class,
        ComputePayableDays::class,
        CalculateEarnings::class,
        Overtime::class,
        Adjustments::class,
        Arrears::class,
        Persist::class,
    ];

    /** A head-wise run that pays by the head's rule or the employee's salary (10.11). */
    public const HEAD_WISE_STAGES = [ResolvePeriod::class, HeadWiseAmount::class, Persist::class];

    /** An upload run: off-cycle, or head-wise with uploaded amounts (10.11). */
    public const UPLOAD_STAGES = [ResolvePeriod::class, UploadedAmounts::class, Persist::class];

    public function __construct(private PayrollRunScope $scope) {}

    public function handle(PayrollRunEmployee $row): PayrollRunEmployee
    {
        $run = $row->run;
        if (! $run->status->isEditable() && $run->status !== PayrollRunStatus::Processing) {
            throw new LogicException("Payroll run {$run->code} is {$run->status->value}: its numbers cannot be calculated again.");
        }

        try {
            $stages = match (true) {
                $run->run_type === PayrollRunType::Regular => self::STAGES,
                $run->isUploadRun() => self::UPLOAD_STAGES,
                $run->run_type === PayrollRunType::HeadWise => self::HEAD_WISE_STAGES,
                default => throw new PayrollException(__(':type runs are not calculated yet.', ['type' => $run->run_type->label()])),
            };

            // Payroll sees every employee of the run, whoever is logged in (or nobody: a queued job).
            $employee = Employee::withoutGlobalScopes()->with(PayrollRunScope::WITH)->findOrFail($row->employee_id);
            // Bank account, holds, entity, leaving: as they are NOW, not when the run was made (10.9).
            $this->scope->refresh($row, $employee);

            app(Pipeline::class)
                ->send(new PayrollContext($run, $row, $employee))
                ->through($stages)
                ->thenReturn();
        } catch (PayrollException $e) {
            $this->fail($row, $e->getMessage());
        }

        return $row;
    }

    private function fail(PayrollRunEmployee $row, string $message): void
    {
        $row->getConnection()->transaction(function () use ($row, $message) {
            PayrollItem::where('payroll_run_employee_id', $row->id)->delete();   // no stale numbers next to an error
            PayrollArrear::where('payroll_run_employee_id', $row->id)->delete();
            PayrollAdjustment::where('payroll_run_id', $row->payroll_run_id)->where('employee_id', $row->employee_id)
                ->update(['payroll_run_id' => null]);                              // a later calculation takes them again

            $row->forceFill([
                'payable_days' => 0, 'lwp_days' => 0, 'absent_days' => 0, 'overtime_hours' => 0, 'joiner_deferred' => false,
                'basic' => Money::zero(), 'gross' => Money::zero(), 'deductions' => Money::zero(), 'net' => Money::zero(),
                'calc_status' => PayrollCalcStatus::Failed,
                'warnings' => null,
                'error' => $message,
                'calculated_at' => now(),
            ])->save();
        });
    }
}
