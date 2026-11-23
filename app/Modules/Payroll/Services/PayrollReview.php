<?php

namespace App\Modules\Payroll\Services;

use App\Core\Support\Money;
use App\Modules\Payroll\Enums\HeadType;
use App\Modules\Payroll\Enums\PayrollCalcStatus;
use App\Modules\Payroll\Enums\PayrollRunType;
use App\Modules\Payroll\Models\PayrollItem;
use App\Modules\Payroll\Models\PayrollRun;
use App\Modules\Payroll\Models\PayrollRunEmployee;
use App\Modules\Payroll\Models\SalaryHead;
use Illuminate\Database\Eloquent\Collection;

/**
 * What the run's review page (10.8) needs besides the rows themselves:
 *
 *     $review->previousRows($run)       employee_id => their row in the PREVIOUS period's regular run (variance)
 *     $review->previousRun($run)        the previous regular run of the same scope and cycle (header totals)
 *     $review->heads($run)              the heads paid in the run: earnings, deductions, information, in head order
 *     $review->exceptions($run)         what a reviewer should look at before submitting, grouped
 *     PayrollReview::change($now, $before)   ['diff' => Money, 'percent' => ?float]
 *
 * "Previous period" = the latest run OF THE SAME KIND (regular with regular; head-wise with the same head; off-cycle
 * with off-cycle) that ended before this one started: at most two months back for regular runs (a half-month run
 * compares with the other half), a year back for the others (last year's festival bonus). Only Ok rows count.
 */
class PayrollReview
{
    /** A net pay that moved this many percent or more against the previous period is an exception. */
    public const VARIANCE_PERCENT = 10;

    /** @return Collection<int, PayrollRunEmployee> keyed by employee_id */
    public function previousRows(PayrollRun $run): Collection
    {
        return PayrollRunEmployee::query()
            ->select('payroll_run_employees.*')
            ->join('payroll_runs as r', 'r.id', '=', 'payroll_run_employees.payroll_run_id')
            ->where('r.run_type', $run->run_type->value)
            ->when($run->salary_head_id !== null, fn ($q) => $q->where('r.salary_head_id', $run->salary_head_id))
            ->where('r.id', '!=', $run->id)
            ->where('r.period_end', '<', $run->period_start->toDateString())
            ->where('r.period_start', '>=', $run->period_start->copy()->subMonthsNoOverflow($this->lookBack($run))->startOfMonth()->toDateString())
            ->where('payroll_run_employees.calc_status', PayrollCalcStatus::Ok->value)
            ->whereIn('payroll_run_employees.employee_id', PayrollRunEmployee::query()->where('payroll_run_id', $run->id)->select('employee_id'))
            ->orderBy('r.period_start')->orderBy('r.id')
            ->with('run:id,code,year,month,cycle,period_start,period_end')
            ->get()
            ->keyBy('employee_id');   // the latest period wins
    }

    /** Months back to look for the previous run. */
    private function lookBack(PayrollRun $run): int
    {
        return $run->run_type === PayrollRunType::Regular ? 2 : 13;
    }

    public function previousRun(PayrollRun $run): ?PayrollRun
    {
        return PayrollRun::query()
            ->where('run_type', $run->run_type)
            ->when($run->salary_head_id !== null, fn ($q) => $q->where('salary_head_id', $run->salary_head_id))
            ->whereKeyNot($run->id)
            ->where('entity_id', $run->entity_id)
            ->where('cycle', $run->cycle)
            ->where('period_end', '<', $run->period_start->toDateString())
            ->orderByDesc('period_start')->orderByDesc('id')
            ->first();
    }

    /** @return Collection<int, SalaryHead> */
    public function heads(PayrollRun $run): Collection
    {
        $order = [HeadType::Earning->value => 1, HeadType::Deduction->value => 2, HeadType::Info->value => 3];

        return SalaryHead::query()
            ->whereIn('id', PayrollItem::query()->where('payroll_run_id', $run->id)->select('salary_head_id'))
            ->ordered()->get()
            ->sortBy(fn (SalaryHead $h) => [$order[$h->type->value], $h->sequence, $h->name])
            ->values();
    }

    /** @return array{diff: Money, percent: float|null} percent null when there was nothing before */
    public static function change(Money $now, ?Money $before): array
    {
        $before ??= Money::zero();
        $diff = $now->subtract($before);

        return [
            'diff' => $diff,
            'percent' => $before->isZero() ? null : round($diff->minor() * 100 / abs($before->minor()), 1),
        ];
    }

    /** "+12.5 %", "−3.0 %", "new". */
    public static function percentLabel(?float $percent): string
    {
        return $percent === null ? __('new') : ($percent > 0 ? '+' : ($percent < 0 ? '−' : '')).number_format(abs($percent), 1).' %';
    }

    public static function isVariance(?float $percent): bool
    {
        return $percent !== null && abs($percent) >= self::VARIANCE_PERCENT;
    }

    /**
     * Groups of rows a reviewer should look at, in this order. Empty groups are left out.
     *
     * @return array<string, array{label: string, hint: string, color: string, items: list<array{row: PayrollRunEmployee, message: string}>}>
     */
    public function exceptions(PayrollRun $run): array
    {
        $rows = $run->employees()
            ->with(['employee' => fn ($q) => $q->withoutGlobalScopes()->select('id', 'code', 'first_name', 'last_name', 'separation_date')])
            ->orderBy('id')->get();
        $previous = $this->previousRows($run);
        $comparable = $previous->isNotEmpty();   // the first payroll ever has nothing to compare with

        $groups = [
            'failed' => [__('Failed'), __('Not calculated: fix the cause and calculate again.'), 'danger'],
            'pending' => [__('Not calculated yet'), __('Calculate the run.'), 'neutral'],
            'warnings' => [__('Warnings'), __('Calculated, but check these.'), 'warning'],
            'variance' => [__('Net pay changed by :p % or more', ['p' => self::VARIANCE_PERCENT]), __('Against the previous period.'), 'warning'],
            'net' => [__('Nothing or less than nothing to pay'), __('Net pay is zero or negative.'), 'danger'],
            'new' => [__('Not in the previous period'), __('Joiners, transfers in, people back from a break.'), 'info'],
            'leaving' => [__('Leaving in this period'), __('Final settlement comes separately.'), 'info'],
            'hold' => [__('On hold'), __('Salary or bank transfer held.'), 'neutral'],
            'bank' => [__('No bank account'), __('Paid by cash or cheque unless one is added.'), 'neutral'],
        ];
        $items = array_fill_keys(array_keys($groups), []);

        foreach ($rows as $row) {
            $add = function (string $group, string $message) use (&$items, $row) {
                $items[$group][] = ['row' => $row, 'message' => $message];
            };

            if ($row->calc_status === PayrollCalcStatus::Failed) {
                $add('failed', (string) $row->error);

                continue;
            }
            if ($row->calc_status === PayrollCalcStatus::Pending) {
                $add('pending', __('Not calculated yet.'));

                continue;
            }

            foreach ($row->warnings ?? [] as $warning) {
                $add('warnings', $warning);
            }

            $before = $previous[$row->employee_id] ?? null;
            if ($before !== null) {
                $change = self::change($row->net, $before->net);
                if (self::isVariance($change['percent'])) {
                    $add('variance', __(':before (:run) → :now (:percent)', [
                        'before' => $before->net->format(), 'run' => $before->run->code, 'now' => $row->net->format(), 'percent' => self::percentLabel($change['percent']),
                    ]));
                }
            } elseif ($comparable) {
                $add('new', __('No row in the previous period.'));
            }

            if (! $row->net->isPositive() && ! $row->joiner_deferred) {
                $add('net', __('Net :net.', ['net' => $row->net->format()]));
            }
            if ($row->is_separated) {
                $add('leaving', __('Leaves on :date.', ['date' => format_date($row->employee->separation_date)]));
            }
            if ($row->is_on_hold || $row->hold_bank_transfer) {
                $add('hold', $row->is_on_hold ? __('Salary on hold.') : __('Bank transfer on hold.'));
            }
            if ($row->bank_account_id === null && ! $row->is_on_hold) {
                $add('bank', __('No primary bank account.'));
            }
        }

        $result = [];
        foreach ($groups as $key => [$label, $hint, $color]) {
            if ($items[$key] !== []) {
                $result[$key] = ['label' => $label, 'hint' => $hint, 'color' => $color, 'items' => $items[$key]];
            }
        }

        return $result;
    }
}
