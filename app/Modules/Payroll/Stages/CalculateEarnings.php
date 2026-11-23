<?php

namespace App\Modules\Payroll\Stages;

use App\Core\Support\Money;
use App\Modules\Payroll\Data\PayrollContext;
use App\Modules\Payroll\Data\PayrollLine;
use App\Modules\Payroll\Enums\PayCycle;
use App\Modules\Payroll\Enums\PayrollItemSource;
use Closure;

/**
 * Stage 4. One line per head of the salary.
 *
 *   actual  the head's monthly amount from the salary, times the cycle's share (a half-month run pays 50%)
 *   amount  actual, but for a head that prorates (salary_heads.prorate): actual × payable days ÷ days in the period
 *           (payable days already lack the days before joining / after leaving, LWP and absence: ComputePayableDays)
 *
 * The salary rows already hold the amounts (a "50% of basic" head was worked out when the salary was saved, 9.4),
 * so nothing is recalculated from the rates here. Annual and one-time heads are paid in head-wise runs (10.11),
 * not in the monthly salary: they are left out with a warning. Formula and per-attendance-day heads are not part
 * of a saved salary, so they are not paid yet.
 *
 * No payable days (a late joiner after the threshold, a whole month of LWP / absence) → no lines at all: not even
 * heads that do not prorate are paid (legacy: "if basic is zero, other heads are not processed").
 */
class CalculateEarnings
{
    public function handle(PayrollContext $ctx, Closure $next): mixed
    {
        if ($ctx->payableDays <= 0) {
            return $next($ctx);
        }

        $prorated = $ctx->payableDays < $ctx->periodDays;

        foreach ($ctx->salary as $row) {
            $head = $row->head;
            if ($head->pay_cycle !== PayCycle::Monthly) {
                $ctx->warn(__(':head is an :cycle head: paid in a head-wise run, left out of the monthly salary.', ['head' => $head->name, 'cycle' => strtolower($head->pay_cycle->label())]));

                continue;
            }

            $actual = $ctx->cycleShare === '100' ? $row->amount : $row->amount->percent($ctx->cycleShare);
            $amount = $head->prorate && $prorated
                ? $actual->multiply(PayrollContext::days($ctx->payableDays))->divide($ctx->periodDays)
                : $actual;

            $ctx->lines[] = new PayrollLine($head, PayrollItemSource::Structure, $amount, $actual, Money::zero());
        }

        return $next($ctx);
    }
}
