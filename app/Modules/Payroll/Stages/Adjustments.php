<?php

namespace App\Modules\Payroll\Stages;

use App\Core\Support\Money;
use App\Modules\Payroll\Data\PayrollContext;
use App\Modules\Payroll\Data\PayrollLine;
use App\Modules\Payroll\Enums\PayrollItemSource;
use App\Modules\Payroll\Models\PayrollAdjustment;
use Closure;

/**
 * Stage 7. The employee's approved one-time earnings / deductions of the run's month (payroll_adjustments),
 * one line each on its own head (the head's type makes it an earning or a deduction). Paid in full: no proration.
 *
 * Takes the ones no run took yet, or that THIS run took before (calculating again). Persist stamps them with the
 * run in its transaction, so two runs never pay the same adjustment (a half-month month: the first run takes it).
 */
class Adjustments
{
    public function handle(PayrollContext $ctx, Closure $next): mixed
    {
        $adjustments = PayrollAdjustment::query()
            ->dueIn($ctx->period, $ctx->run)
            ->where('employee_id', $ctx->employee->id)
            ->with('head')
            ->orderBy('id')
            ->get();

        foreach ($adjustments as $adjustment) {
            $ctx->lines[] = new PayrollLine($adjustment->head, PayrollItemSource::Adjustment, $adjustment->amount, $adjustment->amount, Money::zero());
            $ctx->adjustmentIds[] = $adjustment->id;
        }

        return $next($ctx);
    }
}
