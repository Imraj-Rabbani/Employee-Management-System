<?php

namespace App\Modules\Payroll\Stages;

use App\Core\Support\Money;
use App\Modules\Payroll\Data\ArrearLine;
use App\Modules\Payroll\Data\PayrollContext;
use App\Modules\Payroll\Data\PayrollLine;
use App\Modules\Payroll\Enums\ArrearReason;
use App\Modules\Payroll\Enums\PayrollCalcStatus;
use App\Modules\Payroll\Enums\PayrollItemSource;
use App\Modules\Payroll\Enums\PayrollRunStatus;
use App\Modules\Payroll\Enums\PayrollRunType;
use App\Modules\Payroll\Exceptions\PayrollException;
use App\Modules\Payroll\Models\PayrollArrear;
use App\Modules\Payroll\Models\PayrollItem;
use App\Modules\Payroll\Models\PayrollRunEmployee;
use App\Modules\Payroll\Models\SalaryHead;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pipeline\Pipeline;

/**
 * Stage 8. Back pay for EARLIER periods (legacy: arrears in insertPayroll).
 *
 * Which earlier periods: the employee's rows in earlier REGULAR runs that are approved / locked / disbursed (their
 * numbers are final), calculated Ok, and
 *   - a salary row valid in that period was saved after the row was calculated (a back-dated salary change), or
 *   - the employee joined after the threshold day, so that run paid nothing (joiner_deferred).
 *
 * For each such period the salary is worked out AGAIN with today's salary rows (stages ResolvePeriod,
 * LoadSalaryStructure, CalculateEarnings), keeping the payable days that run used; a late joiner's days are worked
 * out now (ComputePayableDays without the threshold). Per head:
 *
 *   due          what the period pays with today's salary
 *   paid before  that run's salary items (without the arrears they carried) + arrears for that period in OTHER runs
 *   arrear       due − paid before  (negative = a back-dated cut is taken back, with a warning)
 *
 * Only heads marked arrear-eligible. The arrear is added to the head's line of this run (its `arrear` column says how
 * much of the amount is back pay); a head not in today's salary gets its own line. Persist writes the ledger
 * (payroll_arrears), which is what makes a later run pay only what is still missing.
 *
 * The pay scale decides (pays_arrear): off → nothing is paid, with a warning.
 * Not here: arrears for attendance / LWP changes in an earlier period (only salary changes and late joiners).
 */
class Arrears
{
    /** Runs whose numbers are final: what they paid counts as paid. */
    public const PAID = [PayrollRunStatus::Approved, PayrollRunStatus::Locked, PayrollRunStatus::Disbursed];

    public function handle(PayrollContext $ctx, Closure $next): mixed
    {
        $rows = $this->periodsToCorrect($ctx);
        if ($rows->isEmpty()) {
            return $next($ctx);
        }

        if (! ($ctx->payScale?->pays_arrear ?? true)) {
            $ctx->warn(__(':count earlier period(s) have back pay due: not paid (pay scale profile :name pays no arrears).', [
                'count' => $rows->count(), 'name' => $ctx->payScale->name,
            ]));

            return $next($ctx);
        }

        foreach ($rows as $row) {
            try {
                array_push($ctx->arrears, ...$this->arrearsFor($ctx, $row));
            } catch (PayrollException $e) {
                $ctx->warn(__('Arrears for :period not worked out: :message', ['period' => $row->run->period()->label(), 'message' => $e->getMessage()]));
            }
        }

        $this->addToLines($ctx);

        return $next($ctx);
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, PayrollRunEmployee> oldest period first */
    private function periodsToCorrect(PayrollContext $ctx): \Illuminate\Database\Eloquent\Collection
    {
        return PayrollRunEmployee::query()
            ->select('payroll_run_employees.*')
            ->join('payroll_runs as r', 'r.id', '=', 'payroll_run_employees.payroll_run_id')
            ->where('payroll_run_employees.employee_id', $ctx->employee->id)
            ->where('payroll_run_employees.calc_status', PayrollCalcStatus::Ok->value)
            ->where('r.run_type', PayrollRunType::Regular->value)
            ->whereIn('r.status', array_map(fn (PayrollRunStatus $s) => $s->value, self::PAID))
            ->where('r.period_end', '<', $ctx->period->start()->toDateString())
            ->where(fn (Builder $q) => $q
                ->where('payroll_run_employees.joiner_deferred', true)
                ->orWhereExists(fn (QueryBuilder $s) => $s->from('employee_salaries as s')
                    ->whereColumn('s.employee_id', 'payroll_run_employees.employee_id')
                    ->whereColumn('s.updated_at', '>', 'payroll_run_employees.calculated_at')
                    ->whereColumn('s.effective_from', '<=', 'r.period_end')
                    ->where(fn (QueryBuilder $w) => $w->whereNull('s.effective_to')->orWhereColumn('s.effective_to', '>=', 'r.period_start'))))
            ->orderBy('r.period_start')
            ->with('run')
            ->get();
    }

    /** @return list<ArrearLine> */
    private function arrearsFor(PayrollContext $ctx, PayrollRunEmployee $row): array
    {
        $reason = $row->joiner_deferred ? ArrearReason::LateJoiner : ArrearReason::SalaryChange;

        // That period again, with today's salary.
        $again = new PayrollContext($row->run, $row, $ctx->employee);
        $again->ignoreThreshold = true;
        $days = $reason === ArrearReason::LateJoiner
            ? ComputePayableDays::class
            : function (PayrollContext $c, Closure $next) use ($row) {   // the days that run paid stay as they were
                $c->payableDays = (float) $row->payable_days;

                return $next($c);
            };
        app(Pipeline::class)->send($again)
            ->through([ResolvePeriod::class, LoadSalaryStructure::class, $days, CalculateEarnings::class])
            ->thenReturn();

        /** @var array<int, Money> $due */
        $due = [];
        $heads = [];
        foreach ($again->lines as $line) {
            $due[$line->head->id] = ($due[$line->head->id] ?? Money::zero())->add($line->amount);
            $heads[$line->head->id] = $line->head;
        }

        /** @var array<int, Money> $paid */
        $paid = [];
        $items = PayrollItem::query()->where('payroll_run_employee_id', $row->id)->where('source', PayrollItemSource::Structure->value)->with('head')->get();
        foreach ($items as $item) {
            $paid[$item->salary_head_id] = ($paid[$item->salary_head_id] ?? Money::zero())->add($item->amount->subtract($item->arrear));
            $heads[$item->salary_head_id] ??= $item->head;
        }
        $earlier = PayrollArrear::query()->where('for_payroll_run_employee_id', $row->id)
            ->where('payroll_run_id', '!=', $ctx->run->id)->with('head')->get();
        foreach ($earlier as $arrear) {
            $paid[$arrear->salary_head_id] = ($paid[$arrear->salary_head_id] ?? Money::zero())->add($arrear->amount);
            $heads[$arrear->salary_head_id] ??= $arrear->head;
        }

        $lines = [];
        foreach ($heads as $id => $head) {
            /** @var SalaryHead $head */
            if (! $head->arrear_eligible) {
                continue;
            }
            $headDue = $due[$id] ?? Money::zero();
            $headPaid = $paid[$id] ?? Money::zero();
            $difference = $headDue->subtract($headPaid);
            if (! $difference->isZero()) {
                $lines[] = new ArrearLine($head, $row, $reason, $headDue, $headPaid, $difference);
            }
        }

        return $lines;
    }

    /** Each head's back pay goes into its salary line of this run (amount + arrear), or a line of its own. */
    private function addToLines(PayrollContext $ctx): void
    {
        /** @var array<int, Money> $byHead */
        $byHead = [];
        $heads = [];
        foreach ($ctx->arrears as $arrear) {
            $byHead[$arrear->head->id] = ($byHead[$arrear->head->id] ?? Money::zero())->add($arrear->amount);
            $heads[$arrear->head->id] = $arrear->head;
        }

        foreach ($byHead as $id => $amount) {
            $index = null;
            foreach ($ctx->lines as $i => $line) {
                if ($line->head->id === $id && $line->source === PayrollItemSource::Structure) {
                    $index = $i;
                    break;
                }
            }

            if ($index !== null) {
                $line = $ctx->lines[$index];
                $ctx->lines[$index] = new PayrollLine($line->head, $line->source, $line->amount->add($amount), $line->actual, $line->arrear->add($amount));
            } else {
                $ctx->lines[] = new PayrollLine($heads[$id], PayrollItemSource::Arrear, $amount, Money::zero(), $amount);
            }
        }

        $total = array_reduce($ctx->arrears, fn (Money $sum, ArrearLine $a) => $sum->add($a->amount), Money::zero());
        if ($total->isNegative()) {
            $ctx->warn(__('Arrears take back :amount (a back-dated salary cut).', ['amount' => $total->abs()->format()]));
        }
    }
}
