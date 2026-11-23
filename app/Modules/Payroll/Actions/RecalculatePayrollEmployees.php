<?php

namespace App\Modules\Payroll\Actions;

use App\Modules\Payroll\Enums\PayrollCalcStatus;
use App\Modules\Payroll\Enums\PayrollRunStatus;
use App\Modules\Payroll\Enums\PayrollRunType;
use App\Modules\Payroll\Models\PayrollRun;
use InvalidArgumentException;

/**
 * Calculates ONE or a few employees of a run again, right away (no queue), e.g. after fixing a salary (PR-05).
 * The whole run: ProcessPayrollRun.
 *
 *     if ($problem = $action->problem($run, $rowIds)) { show it } else { $action->handle($run, $rowIds) }
 *
 * Only a regular run in Draft or Processed, at most MAX employees (more → calculate the whole run). Afterwards the
 * run's totals are added up again; a Draft run in which nobody is pending any more becomes Processed.
 */
class RecalculatePayrollEmployees
{
    /** More than this: calculate the whole run (queued) instead. */
    public const MAX = 50;

    public function __construct(private CalculateEmployeePayroll $calculate) {}

    /** @param  list<int>  $rowIds  payroll_run_employees ids */
    public function problem(PayrollRun $run, array $rowIds): ?string
    {
        return match (true) {
            $run->run_type === PayrollRunType::FinalSettlement => __(':type runs cannot be calculated yet.', ['type' => $run->run_type->label()]),
            ! $run->status->isEditable() => __('This run is :status, so it cannot be calculated again.', ['status' => strtolower($run->status->label())]),
            $rowIds === [] => __('Tick the employees to calculate again.'),
            count($rowIds) > self::MAX => __('At most :max employees at a time: calculate the whole run instead.', ['max' => self::MAX]),
            $run->employees()->whereKey($rowIds)->count() !== count(array_unique($rowIds)) => __('These employees are not all in this run.'),
            default => null,
        };
    }

    /**
     * @param  list<int>  $rowIds
     * @return int how many were calculated Ok (the others failed, with their reason on the row)
     */
    public function handle(PayrollRun $run, array $rowIds): int
    {
        if ($problem = $this->problem($run, $rowIds)) {
            throw new InvalidArgumentException($problem);
        }

        $ok = 0;
        foreach ($run->employees()->whereKey($rowIds)->get() as $row) {
            $row->setRelation('run', $run);
            if ($this->calculate->handle($row)->calc_status === PayrollCalcStatus::Ok) {
                $ok++;
            }
        }

        $run->refreshTotals();
        if ($run->status === PayrollRunStatus::Draft && ! $run->employees()->where('calc_status', PayrollCalcStatus::Pending->value)->exists()) {
            $run->forceFill(['status' => PayrollRunStatus::Processed, 'processed_at' => now()])->save();
        }

        return $ok;
    }
}
