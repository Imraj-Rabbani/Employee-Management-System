<?php

namespace App\Modules\Payroll\Actions;

use App\Core\Support\Money;
use App\Core\Support\Period;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Payroll\Enums\HeadType;
use App\Modules\Payroll\Enums\PayrollRunType;
use App\Modules\Payroll\Models\PayrollAdjustment;
use App\Modules\Payroll\Models\PayrollRun;
use App\Modules\Payroll\Models\SalaryHead;
use App\Modules\Workflow\Exceptions\WorkflowException;
use App\Modules\Workflow\Services\WorkflowEngine;
use Illuminate\Support\Facades\Auth;

/**
 * Asks for a payroll adjustment (new, or changed after a send-back) and withdraws it. The ONE way
 * payroll_adjustments are written: Payroll → Adjustments (form and Excel import).
 *
 *     if ($problem = $action->problem($employee, $head, $values)) { show it } else { $action->handle($employee, $head, $values) }
 *
 * Asking: an active earning or deduction head (not the basic, not an information head), amount > 0, a reason,
 * employed on at least one day of the month, and that month's payroll not closed (a locked / disbursed regular
 * run with this employee). Saved and sent for approval in ONE transaction.
 *
 * Withdrawing: until a run took it; a run that is still a draft / processed lets go of it (calculate it again).
 */
class RequestAdjustment
{
    /** Largest amount (DECIMAL(15,2), with room). */
    public const MAX_AMOUNT = '9999999999.99';

    public function __construct(private WorkflowEngine $engine) {}

    /**
     * @param  array{year: int|string, month: int|string, amount: string, reason: string}  $values
     * @return array{field: string, message: string}|null field = salary_head_id, year, month, amount, reason
     */
    public function problem(Employee $employee, SalaryHead $head, array $values, ?PayrollAdjustment $existing = null): ?array
    {
        if ($existing !== null && ! $existing->isEditable()) {
            return ['field' => 'amount', 'message' => __('This adjustment is waiting for approval or already decided, so it cannot be changed.')];
        }
        if (! $head->is_active || $head->is_basic || $head->type === HeadType::Info) {
            return ['field' => 'salary_head_id', 'message' => __('Choose an active earning or deduction head (not the basic).')];
        }

        $year = (int) $values['year'];
        $month = (int) $values['month'];
        if ($year < 2000 || $year > 2100 || $month < 1 || $month > 12) {
            return ['field' => 'month', 'message' => __('Choose a valid month.')];
        }

        $typed = str_replace(',', '', trim((string) $values['amount']));
        if (preg_match('/^\d+(\.\d{1,2})?$/', $typed) !== 1) {
            return ['field' => 'amount', 'message' => __('Amount: a number with at most 2 decimals.')];
        }
        $amount = Money::of($typed);
        if (! $amount->isPositive() || $amount->compare(Money::of(self::MAX_AMOUNT)) > 0) {
            return ['field' => 'amount', 'message' => __('Amount: more than 0 (a deduction is still a positive amount; the head makes it a deduction).')];
        }
        if (trim((string) $values['reason']) === '') {
            return ['field' => 'reason', 'message' => __('Give a reason.')];
        }

        $period = Period::of($year, $month);
        if ($employee->join_date->toDateString() > $period->end()->toDateString()
            || ($employee->separation_date !== null && $employee->separation_date->toDateString() < $period->start()->toDateString())) {
            return ['field' => 'month', 'message' => __(':name is not employed in :month.', ['name' => $employee->name, 'month' => $period->label()])];
        }

        $closed = PayrollRun::query()->where('year', $year)->where('month', $month)->where('run_type', PayrollRunType::Regular)
            ->get()->first(fn (PayrollRun $run) => $run->status->isFinal() && $run->employees()->where('employee_id', $employee->id)->exists());
        if ($closed !== null) {
            return ['field' => 'month', 'message' => __('Payroll for :month is closed (:run). Choose a later month.', ['month' => $period->label(), 'run' => $closed->code])];
        }

        return null;
    }

    /**
     * Saves and sends for approval in ONE transaction: nothing is kept when nobody can approve it.
     *
     * @param  array{year: int|string, month: int|string, amount: string, reason: string}  $values
     *
     * @throws WorkflowException
     */
    public function handle(Employee $employee, SalaryHead $head, array $values, ?PayrollAdjustment $existing = null): PayrollAdjustment
    {
        return (new PayrollAdjustment)->getConnection()->transaction(function () use ($employee, $head, $values, $existing) {
            $adjustment = $existing ?? new PayrollAdjustment(['requested_by_user_id' => Auth::id()]);
            $adjustment->fill([
                'employee_id' => $employee->id,
                'salary_head_id' => $head->id,
                'year' => (int) $values['year'],
                'month' => (int) $values['month'],
                'amount' => Money::of(trim((string) $values['amount'])),
                'reason' => trim((string) $values['reason']),
            ])->save();

            $adjustment->submitForApproval();

            return $adjustment;
        });
    }

    public function withdrawProblem(PayrollAdjustment $adjustment): ?string
    {
        $run = $adjustment->run;

        return match (true) {
            ! $adjustment->isActive() => __('This adjustment was already rejected or withdrawn.'),
            $run !== null && ! $run->status->isEditable() => __('Payroll run :run already took this adjustment.', ['run' => $run->code]),
            default => null,
        };
    }

    /** A draft / processed run that took it lets go of it; its numbers are right again after calculating it again. */
    public function withdraw(PayrollAdjustment $adjustment): void
    {
        $adjustment->getConnection()->transaction(function () use ($adjustment) {
            $adjustment->forceFill(['withdrawn_at' => now(), 'withdrawn_by_user_id' => Auth::id(), 'payroll_run_id' => null])->save();
            $this->engine->withdraw($adjustment);
        });
    }
}
