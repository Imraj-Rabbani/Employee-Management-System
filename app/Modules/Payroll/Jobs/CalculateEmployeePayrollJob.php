<?php

namespace App\Modules\Payroll\Jobs;

use App\Core\Support\Money;
use App\Core\Tenancy\TenantAware;
use App\Core\Tenancy\TenantAwareJob;
use App\Modules\Payroll\Actions\CalculateEmployeePayroll;
use App\Modules\Payroll\Enums\PayrollCalcStatus;
use App\Modules\Payroll\Models\PayrollAdjustment;
use App\Modules\Payroll\Models\PayrollArrear;
use App\Modules\Payroll\Models\PayrollItem;
use App\Modules\Payroll\Models\PayrollRunEmployee;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Calculates ONE employee of a payroll run, as one job of the run's batch (ProcessPayrollRun). Needs a queue worker.
 * The work is in Actions\CalculateEmployeePayroll; this only runs it inside the right company.
 *
 * A problem with the employee's data (no salary ...) is NOT a failed job: the action marks the row Failed and the job
 * ends normally. A failed job = a bug or a lost database connection: failed() then marks the row Failed too, so the
 * employee shows up in the run's list of failures instead of staying "pending" forever.
 */
class CalculateEmployeePayrollJob implements ShouldQueue
{
    use Batchable;
    use Queueable;
    use TenantAwareJob;

    /** No automatic retries: calculating again is a click on Calculate. */
    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public int $runEmployeeId)
    {
        $this->rememberTenant();
    }

    public function handle(CalculateEmployeePayroll $calculate): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $row = PayrollRunEmployee::find($this->runEmployeeId);
        if ($row !== null) {
            $calculate->handle($row);
        }
    }

    /** Called by the queue worker after an exception, OUTSIDE the tenant middleware: switch to the company again. */
    public function failed(Throwable $e): void
    {
        (new TenantAware)->handle($this, function () use ($e) {
            $row = PayrollRunEmployee::find($this->runEmployeeId);
            if ($row === null) {
                return;
            }

            report($e);
            PayrollItem::where('payroll_run_employee_id', $row->id)->delete();
            PayrollArrear::where('payroll_run_employee_id', $row->id)->delete();
            PayrollAdjustment::where('payroll_run_id', $row->payroll_run_id)->where('employee_id', $row->employee_id)->update(['payroll_run_id' => null]);
            $row->forceFill([
                'basic' => Money::zero(), 'gross' => Money::zero(), 'deductions' => Money::zero(), 'net' => Money::zero(),
                'calc_status' => PayrollCalcStatus::Failed,
                'error' => __('The calculation stopped unexpectedly: :message', ['message' => mb_substr($e->getMessage(), 0, 300)]),
                'calculated_at' => now(),
            ])->save();
        });
    }
}
