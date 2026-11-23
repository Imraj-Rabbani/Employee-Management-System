<?php

namespace App\Modules\Payroll\Stages;

use App\Modules\Payroll\Data\ArrearLine;
use App\Modules\Payroll\Data\PayrollContext;
use App\Modules\Payroll\Data\PayrollLine;
use App\Modules\Payroll\Data\SalaryBreakdown;
use App\Modules\Payroll\Data\SalaryLine;
use App\Modules\Payroll\Enums\PayrollCalcStatus;
use App\Modules\Payroll\Models\PayrollAdjustment;
use App\Modules\Payroll\Models\PayrollArrear;
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
 * meanwhile) are let go. Arrears (back pay for earlier periods) are written to the payroll_arrears ledger.
 * A negative net pay is saved, with a warning for the reviewer.
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

        $arrears = array_map(fn (ArrearLine $a) => [
            'payroll_run_id' => $ctx->run->id,
            'payroll_run_employee_id' => $ctx->row->id,
            'employee_id' => $ctx->employee->id,
            'salary_head_id' => $a->head->id,
            'for_payroll_run_id' => $a->forRow->payroll_run_id,
            'for_payroll_run_employee_id' => $a->forRow->id,
            'for_period_start' => $a->forRow->run->period_start->toDateString(),
            'for_period_end' => $a->forRow->run->period_end->toDateString(),
            'reason' => $a->reason->value,
            'due' => $a->due->toDecimal(),
            'paid_before' => $a->paidBefore->toDecimal(),
            'amount' => $a->amount->toDecimal(),
            'created_at' => $now,
            'updated_at' => $now,
        ], $ctx->arrears);

        $ctx->row->getConnection()->transaction(function () use ($ctx, $totals, $items, $arrears, $now) {
            PayrollItem::where('payroll_run_employee_id', $ctx->row->id)->delete();
            PayrollItem::insert($items);
            PayrollArrear::where('payroll_run_employee_id', $ctx->row->id)->delete();
            PayrollArrear::insert($arrears);

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
                'joiner_deferred' => $ctx->joinerDeferred,
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
