<?php

namespace App\Modules\Payroll\Concerns;

use App\Modules\Payroll\Enums\PayrollRunStatus;
use App\Modules\Payroll\Exceptions\PayrollLockedException;
use App\Modules\Payroll\Models\PayrollRun;

/**
 * The OBSERVER GUARD (10.10) for the rows that make up a run's numbers (payroll_run_employees, payroll_items,
 * payroll_arrears): saving or deleting one through Eloquent while its run is no longer Draft / Processing /
 * Processed throws PayrollLockedException. Bulk writes (insert / query delete) skip model events; they are done only
 * by the calculation, which checks the run's status itself (CalculateEmployeePayroll).
 */
trait GuardedByRunStatus
{
    /** Statuses in which a run's numbers may still change. */
    public const OPEN = [PayrollRunStatus::Draft, PayrollRunStatus::Processing, PayrollRunStatus::Processed];

    protected static function bootGuardedByRunStatus(): void
    {
        $guard = function (self $model): void {
            $run = PayrollRun::query()->whereKey($model->payroll_run_id)->first(['id', 'code', 'status']);
            if ($run !== null && ! in_array($run->status, self::OPEN, true)) {
                throw PayrollLockedException::for($run->code, $run->status->value);
            }
        };

        static::saving($guard);
        static::deleting($guard);
    }
}
