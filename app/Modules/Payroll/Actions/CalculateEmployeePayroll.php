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
use App\Modules\Payroll\Models\PayrollItem;
use App\Modules\Payroll\Models\PayrollRunEmployee;
use App\Modules\Payroll\Stages\Adjustments;
use App\Modules\Payroll\Stages\CalculateEarnings;
use App\Modules\Payroll\Stages\ComputePayableDays;
use App\Modules\Payroll\Stages\LoadSalaryStructure;
use App\Modules\Payroll\Stages\Overtime;
use App\Modules\Payroll\Stages\Persist;
use App\Modules\Payroll\Stages\ResolvePeriod;
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
        Persist::class,
    ];

    public function handle(PayrollRunEmployee $row): PayrollRunEmployee
    {
        $run = $row->run;
        if (! $run->status->isEditable() && $run->status !== PayrollRunStatus::Processing) {
            throw new LogicException("Payroll run {$run->code} is {$run->status->value}: its numbers cannot be calculated again.");
        }

        try {
            if ($run->run_type !== PayrollRunType::Regular) {
                throw new PayrollException(__(':type runs are not calculated yet.', ['type' => $run->run_type->label()]));
            }

            // Payroll sees every employee of the run, whoever is logged in (or nobody: a queued job).
            $employee = Employee::withoutGlobalScopes()->findOrFail($row->employee_id);

            app(Pipeline::class)
                ->send(new PayrollContext($run, $row, $employee))
                ->through(self::STAGES)
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
            PayrollAdjustment::where('payroll_run_id', $row->payroll_run_id)->where('employee_id', $row->employee_id)
                ->update(['payroll_run_id' => null]);                              // a later calculation takes them again

            $row->forceFill([
                'payable_days' => 0, 'lwp_days' => 0, 'absent_days' => 0, 'overtime_hours' => 0,
                'basic' => Money::zero(), 'gross' => Money::zero(), 'deductions' => Money::zero(), 'net' => Money::zero(),
                'calc_status' => PayrollCalcStatus::Failed,
                'warnings' => null,
                'error' => $message,
                'calculated_at' => now(),
            ])->save();
        });
    }
}
