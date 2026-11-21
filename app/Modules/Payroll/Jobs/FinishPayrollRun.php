<?php

namespace App\Modules\Payroll\Jobs;

use App\Core\Tenancy\TenantAware;
use App\Core\Tenancy\TenantAwareJob;
use App\Modules\Payroll\Enums\PayrollRunStatus;
use App\Modules\Payroll\Models\PayrollRun;
use Illuminate\Bus\Batch;

/**
 * The batch's "finally" step (ProcessPayrollRun): when every employee job has ended, add up the run's totals and move
 * it from Processing to Processed. An object with __invoke instead of a closure, because the batch stores it in the
 * database and a closure would need the whole PHP scope serialised.
 *
 * Runs in a queue worker with no company active, so it switches to the run's company like a job does.
 * "finally" (not "then"): it must also run when some employees failed, so the run never stays Processing.
 */
class FinishPayrollRun
{
    use TenantAwareJob;

    public function __construct(public int $runId)
    {
        $this->rememberTenant();
    }

    public function __invoke(Batch $batch): void
    {
        (new TenantAware)->handle($this, function () use ($batch) {
            $run = PayrollRun::find($this->runId);
            if ($run === null || $run->status !== PayrollRunStatus::Processing) {
                return;
            }
            // The run was started again: its batch is another one now, and this (cancelled) one must not finish it.
            // batch_id is NULL when the batch ended before ProcessPayrollRun could store it (sync queue, very fast worker).
            if ($run->batch_id !== null && $run->batch_id !== $batch->id) {
                return;
            }

            $run->refreshTotals();
            $run->forceFill(['processed_at' => now()])->save();
            $run->moveTo(PayrollRunStatus::Processed);
        });
    }
}
