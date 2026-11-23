<?php

namespace App\Modules\Payroll\Stages;

use App\Modules\Payroll\Data\PayrollContext;
use App\Modules\Payroll\Data\PayrollLine;
use App\Modules\Payroll\Data\SalaryBreakdown;
use App\Modules\Payroll\Data\SalaryLine;
use App\Modules\Payroll\Enums\PayrollCalcStatus;
use App\Modules\Payroll\Models\PayrollAdjustment;
use App\Modules\Payroll\Models\PayrollItem;
use Closure;

/**
 * Stage 12 (the last). Totals and saving. Amounts are exact to the cent already (Money), so the only "rounding"
 * is that each line was rounded half up when it was worked out.
 *
 * Totals come from SalaryBreakdown, the same rule as a salary: gross = earnings counted in gross, net = gross −
 * deductions, information heads in neither. Replaces the employee's earlier items: calculating again (10.9) gives
 * the same rows, never double ones. One transaction: a half-written payslip cannot exist.
 *
 * Adjustments: the ones paid now are stamped with the run; ones this run took before but no longer pays (withdrawn
 * meanwhile) are let go. A negative net pay is saved, with a warning for the reviewer.
 */
class Persist
{
    public function handle(PayrollContext $ctx, Closure $next): mixed
    {
        $totals = SalaryBreakdown::fromLines(array_map(
            fn (PayrollLine $line) => new SalaryLine($line->head, $line->source->label(), $line->amount),
            $ctx->lines,
        ));

        if ($totals->net->isNegative()) {
            $ctx->warn(__('Net pay is negative (:net): deductions are more than the gross.', ['net' => $totals->net->format()]));
        }

        $now = now();
        $items = array_map(fn (PayrollLine $line) => [
            'payroll_run_id' => $ctx->run->id,
            'payroll_run_employee_id' => $ctx->row->id,
            'employee_id' => $ctx->employee->id,
            'salary_head_id' => $line->head->id,
            'amount' => $line->amount->toDecimal(),
            'actual' => $line->actual->toDecimal(),
            'arrear' => $line->arrear->toDecimal(),
            'source' => $line->source->value,
            'created_at' => $now,
            'updated_at' => $now,
        ], $ctx->lines);

        $ctx->row->getConnection()->transaction(function () use ($ctx, $totals, $items, $now) {
            PayrollItem::where('payroll_run_employee_id', $ctx->row->id)->delete();
            PayrollItem::insert($items);

            PayrollAdjustment::query()->where('payroll_run_id', $ctx->run->id)->where('employee_id', $ctx->employee->id)
                ->whereNotIn('id', $ctx->adjustmentIds)->update(['payroll_run_id' => null]);
            if ($ctx->adjustmentIds !== []) {
                PayrollAdjustment::query()->whereIn('id', $ctx->adjustmentIds)->update(['payroll_run_id' => $ctx->run->id]);
            }

            $ctx->row->forceFill([
                'period_days' => $ctx->periodDays,
                'payable_days' => $ctx->payableDays,
                'lwp_days' => $ctx->lwpDays,
                'absent_days' => $ctx->absentDays,
                'overtime_hours' => $ctx->overtimeHours,
                'basic' => $totals->basic,
                'gross' => $totals->gross,
                'deductions' => $totals->deductions,
                'net' => $totals->net,
                'calc_status' => PayrollCalcStatus::Ok,
                'warnings' => $ctx->warnings === [] ? null : $ctx->warnings,
                'error' => null,
                'calculated_at' => $now,
            ])->save();
        });

        return $next($ctx);
    }
}
