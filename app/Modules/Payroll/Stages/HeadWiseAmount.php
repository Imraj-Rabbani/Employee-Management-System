<?php

namespace App\Modules\Payroll\Stages;

use App\Core\Support\Money;
use App\Modules\Payroll\Data\PayrollContext;
use App\Modules\Payroll\Data\PayrollLine;
use App\Modules\Payroll\Data\SalaryBreakdown;
use App\Modules\Payroll\Enums\AmountBasis;
use App\Modules\Payroll\Enums\CalcMethod;
use App\Modules\Payroll\Enums\PayrollItemSource;
use App\Modules\Payroll\Exceptions\PayrollException;
use App\Modules\Payroll\Models\EmployeeSalary;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Collection;

/**
 * The one stage of a head-wise run that does not use uploaded amounts (legacy: scheduled disbursements). Pays the
 * run's head once:
 *
 *   head rule   fixed → the head's amount; % of basic / % of gross → the rate of the employee's salary valid on the
 *               period's last working day (monthly basic / gross, not prorated)
 *   salary      the employee's own amount for that head (an annual / one-time head in their salary)
 *
 * prorate_by_service: someone who joined during the run's year gets days(joining → 31 Dec) ÷ days in the year of it.
 * Nothing to pay (no amount for the head in the salary, a 0 rule) → no line, a warning; the row is still Ok.
 */
class HeadWiseAmount
{
    public function handle(PayrollContext $ctx, Closure $next): mixed
    {
        $run = $ctx->run;
        $head = $run->head;
        $ctx->payableDays = (float) ((int) $ctx->workFrom->diffInDays($ctx->workTo) + 1);

        $salary = EmployeeSalary::query()->where('employee_id', $ctx->employee->id)->effectiveOn($ctx->workTo)->with('head')->get();
        $amount = match ($run->amount_basis) {
            AmountBasis::Salary => $salary->firstWhere('salary_head_id', $head->id)->amount ?? Money::zero(),
            default => $this->byRule($ctx, $salary),
        };

        if ($run->prorate_by_service && $amount->isPositive()) {
            $yearStart = CarbonImmutable::create($run->year, 1, 1);
            $yearEnd = $yearStart->endOfYear()->startOfDay();
            $join = CarbonImmutable::parse($ctx->employee->join_date->toDateString());
            if ($join->gt($yearStart)) {
                $full = $amount;
                $amount = $amount->multiply((string) ((int) $join->diffInDays($yearEnd) + 1))->divide((int) $yearStart->diffInDays($yearEnd) + 1);
                $ctx->warn(__('Joined :date: :amount of :full (share of the year).', ['date' => format_date($join), 'amount' => $amount->format(), 'full' => $full->format()]));
            }
        }

        if ($amount->isPositive()) {
            $ctx->lines[] = new PayrollLine($head, PayrollItemSource::Structure, $amount, $amount, Money::zero());
        } else {
            $ctx->warn(__('Nothing to pay for :head.', ['head' => $head->name]));
        }

        return $next($ctx);
    }

    /** @param  Collection<int, EmployeeSalary>  $salary */
    private function byRule(PayrollContext $ctx, $salary): Money
    {
        $head = $ctx->run->head;
        if ($head->calc_method === CalcMethod::Fixed) {
            return $head->amount ?? Money::zero();
        }
        if ($salary->isEmpty() || ! $salary->contains(fn (EmployeeSalary $r) => $r->head->is_basic)) {
            throw new PayrollException(__('No salary on :date: :head is a percentage of it.', ['date' => format_date($ctx->workTo), 'head' => $head->name]));
        }
        $breakdown = EmployeeSalary::breakdown($salary);
        /** @var SalaryBreakdown $breakdown */
        $base = $head->calc_method === CalcMethod::PercentOfGross ? $breakdown->gross : $breakdown->basic;

        return $base->percent((string) $head->rate);
    }
}
