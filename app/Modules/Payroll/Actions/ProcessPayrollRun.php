<?php

namespace App\Modules\Payroll\Actions;

use App\Modules\Payroll\Enums\PayrollCalcStatus;
use App\Modules\Payroll\Enums\PayrollRunStatus;
use App\Modules\Payroll\Enums\PayrollRunType;
use App\Modules\Payroll\Jobs\CalculateEmployeePayrollJob;
use App\Modules\Payroll\Jobs\FinishPayrollRun;
use App\Modules\Payroll\Models\PayrollRun;
use Illuminate\Support\Facades\Bus;
use InvalidArgumentException;
use Throwable;

/**
 * Starts the calculation of a run: ONE queued job per employee, in a Laravel job batch (Architecture §9.2).
 *
 *     $action->problem($run)    null, or a message for HR
 *     $action->handle($run)     the run, now Processing
 *
 *   draft / processed → processing → (the batch ends) → processed     FinishPayrollRun adds up the totals
 *
 * The same action calculates again (10.9): every employee goes back to "pending" first, so the progress bar starts at
 * 0 and old numbers are replaced one by one. allowFailures(): one employee's job failing does not cancel the others.
 * A run stuck in Processing (its batch is gone or over, or nothing happened for STUCK_AFTER_MINUTES: the queue was
 * emptied, the worker never ran) may be started again; the old batch is cancelled, so its leftover jobs do nothing
 * and its finish step (FinishPayrollRun) ignores the run.
 * Needs a queue worker (php artisan queue:work); without one the run stays Processing, with everybody pending.
 */
class ProcessPayrollRun
{
    /** A Processing run this old with a batch that is still open counts as stuck. */
    public const STUCK_AFTER_MINUTES = 30;

    public function problem(PayrollRun $run): ?string
    {
        return match (true) {
            $run->run_type !== PayrollRunType::Regular => __(':type runs cannot be calculated yet.', ['type' => $run->run_type->label()]),
            ! $run->status->isEditable() && $run->status !== PayrollRunStatus::Processing => __('This run is :status, so it cannot be calculated again.', ['status' => strtolower($run->status->label())]),
            $run->status === PayrollRunStatus::Processing && $this->isRunning($run) => __('This run is being calculated right now.'),
            ! $run->employees()->exists() => __('This run has no employees.'),
            default => null,
        };
    }

    public function handle(PayrollRun $run): PayrollRun
    {
        if ($problem = $this->problem($run)) {
            throw new InvalidArgumentException($problem);
        }

        $previous = $run->status;
        if ($run->batch_id) {
            Bus::findBatch($run->batch_id)?->cancel();   // a stuck run's old batch
        }
        $run->getConnection()->transaction(function () use ($run) {
            $run->employees()->update(['calc_status' => PayrollCalcStatus::Pending->value, 'error' => null]);
            $run->forceFill(['status' => PayrollRunStatus::Processing, 'batch_id' => null, 'processed_at' => null])->save();
        });

        try {
            $jobs = $run->employees()->pluck('id')->map(fn (int $id) => new CalculateEmployeePayrollJob($id))->all();

            $batch = Bus::batch($jobs)
                ->name('Payroll '.$run->code)
                ->allowFailures()
                ->finally(new FinishPayrollRun($run->id))
                ->dispatch();
        } catch (Throwable $e) {
            $run->forceFill(['status' => $previous])->save();   // nothing was queued: back to where it was

            throw $e;
        }

        // With the sync queue (tests) the batch is already over and the run Processed: leave that alone.
        PayrollRun::whereKey($run->id)->where('status', PayrollRunStatus::Processing->value)->update(['batch_id' => $batch->id]);

        return $run->refresh();
    }

    private function isRunning(PayrollRun $run): bool
    {
        $batch = $run->batch_id ? Bus::findBatch($run->batch_id) : null;

        return $batch !== null && ! $batch->finished() && ! $batch->cancelled()
            && $run->updated_at->gt(now()->subMinutes(self::STUCK_AFTER_MINUTES));
    }
}
