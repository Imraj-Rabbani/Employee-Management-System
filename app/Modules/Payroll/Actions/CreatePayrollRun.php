<?php

namespace App\Modules\Payroll\Actions;

use App\Models\User;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\Entity;
use App\Modules\Hrm\Services\EmployeeVisibility;
use App\Modules\Payroll\Data\NewPayrollRun;
use App\Modules\Payroll\Enums\AmountBasis;
use App\Modules\Payroll\Enums\CalcMethod;
use App\Modules\Payroll\Enums\HeadType;
use App\Modules\Payroll\Enums\PayrollRunType;
use App\Modules\Payroll\Models\PayrollRun;
use App\Modules\Payroll\Models\PayrollRunEmployee;
use App\Modules\Payroll\Models\SalaryHead;
use App\Modules\Payroll\Services\PayrollRunScope;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

/**
 * Makes a new payroll run in DRAFT, with its employees (the scope) already listed. Calculating it is 10.3 / 10.4.
 *
 *     $action->problem($input, $user)   null, or ['field' => ..., 'message' => ...] written for HR
 *     $action->handle($input, $user)    the new PayrollRun
 *
 * Who is in the run: employees of the scope (the entity, or the whole company; only the given codes when a list is
 * given) who are employed in at least one day of the period: joined on or before its last day and not separated
 * before its first day. Suspended employees and leavers of this period are in (the calculation deals with them).
 * Each row gets a snapshot: entity, primary bank account, hold flags, "left in this period".
 *
 * Duplicate guard ("only one regular run per period and scope", PR-01). For a REGULAR run, and for a HEAD-WISE run
 * of the same head:
 *   1. no other such run whose period overlaps this one (a monthly run overlaps both half-month runs) for the same
 *      entity, and none at all when one side is the whole company;
 *   2. and no employee of the new scope may already be in such a run (two entities of different types, e.g.
 *      Department "Sales" and Branch "Dhaka", share people).
 * Off-cycle and final-settlement runs may be made as often as needed.
 */
class CreatePayrollRun
{
    public function __construct(
        private EmployeeVisibility $visibility,
        private PayrollRunScope $runScope,
    ) {}

    /** @return array{field: string, message: string}|null */
    public function problem(NewPayrollRun $input, User $user): ?array
    {
        $limit = $this->visibility->entityIdsFor($user);   // null = may see everyone
        if ($input->entityId === null && $limit !== null) {
            return ['field' => 'entity_id', 'message' => __('Choose your entity: you may not run payroll for the whole company.')];
        }
        if ($input->entityId !== null && ($limit !== null && ! in_array($input->entityId, $limit, true) || ! Entity::whereKey($input->entityId)->exists())) {
            return ['field' => 'entity_id', 'message' => __('Choose an entity from the list.')];
        }

        if ($input->type->needsHead()) {
            $head = $input->salaryHeadId ? SalaryHead::find($input->salaryHeadId) : null;
            if ($head === null || $head->type !== HeadType::Earning || ! $head->is_active || $head->is_basic) {
                return ['field' => 'salary_head_id', 'message' => __('Choose the earning head this run pays (not the basic).')];
            }
            if ($input->basis() === AmountBasis::HeadRule && ! in_array($head->calc_method, [CalcMethod::Fixed, CalcMethod::PercentOfBasic, CalcMethod::PercentOfGross], true)) {
                return ['field' => 'amount_basis', 'message' => __(':head has no rule the run can use (fixed, % of basic or % of gross). Choose another basis.', ['head' => $head->name])];
            }
        }
        if ($input->basis() === AmountBasis::Upload && $input->employeeCodes !== []) {
            return ['field' => 'employee_codes', 'message' => __('An upload run takes the employees of its file: leave the codes empty.')];
        }
        if ($input->type === PayrollRunType::HeadWise && $input->joinedBefore !== null && $input->joinedBefore->gt($input->period->end()->addDay())) {
            return ['field' => 'joined_before', 'message' => __('"Joined before" cannot be after the period.')];
        }

        if ($input->payDate !== null && $input->payDate->lt($input->period->start())) {
            return ['field' => 'pay_date', 'message' => __('The pay date cannot be before the period starts.')];
        }

        if ($input->employeeCodes !== []) {
            $found = $this->scope($input)->pluck('code')->all();
            $missing = array_values(array_diff($input->employeeCodes, $found));
            if ($missing !== []) {
                return ['field' => 'employee_codes', 'message' => __('Not found in this scope, or not employed in :period: :codes.', [
                    'period' => $input->period->label(), 'codes' => implode(', ', array_slice($missing, 0, 10)),
                ])];
            }
        }

        if ($clash = $this->clashingRun($input)) {
            return ['field' => 'period', 'message' => __(':code already pays :period for :scope. Open that run, or delete it first.', [
                'code' => $clash->code, 'period' => $input->period->label(), 'scope' => $clash->entity->name ?? __('the whole company'),
            ])];
        }

        // Upload runs: checked per employee when the file is uploaded (RunAmountImporter).
        if ($this->guarded($input) && $input->basis()?->usesScope() !== false) {
            $taken = PayrollRunEmployee::query()
                ->whereIn('payroll_run_id', $this->overlappingRuns($input)->select('id'))
                ->whereIn('employee_id', $this->scope($input)->select('employees.id'))
                ->with('run:id,code')->get();
            if ($taken->isNotEmpty()) {
                return ['field' => 'entity_id', 'message' => trans_choice(
                    ':count employee of this scope is already in :codes for this period.|:count employees of this scope are already in :codes for this period.',
                    $taken->count(),
                    ['codes' => $taken->pluck('run.code')->unique()->implode(', ')],
                )];
            }
        }

        if ($input->basis()?->usesScope() !== false && ! $this->scope($input)->exists()) {
            return ['field' => 'entity_id', 'message' => __('Nobody in this scope is employed in :period.', ['period' => $input->period->label()])];
        }

        return null;
    }

    public function handle(NewPayrollRun $input, User $user): PayrollRun
    {
        return (new PayrollRun)->getConnection()->transaction(function () use ($input, $user) {
            // Lock the runs of this month first, THEN check: two people making the same run at once must not both
            // pass the guard (the second one waits here and then sees the first one's run).
            PayrollRun::where('year', $input->period->year)->where('month', $input->period->month)->lockForUpdate()->get(['id']);
            if ($problem = $this->problem($input, $user)) {
                throw new InvalidArgumentException($problem['message']);
            }

            $run = new PayrollRun([
                'code' => $this->nextCode($input),
                'run_type' => $input->type,
                'salary_head_id' => $input->type->needsHead() ? $input->salaryHeadId : null,
                'amount_basis' => $input->basis(),
                'religions' => $input->eligibility()['religions'] ?: null,
                'confirmed_only' => $input->eligibility()['confirmed_only'],
                'joined_before' => $input->eligibility()['joined_before'],
                'prorate_by_service' => $input->type === PayrollRunType::HeadWise && $input->prorateByService,
                'entity_id' => $input->entityId,
                'limited_to_list' => $input->employeeCodes !== [],
                'pay_date' => $input->payDate,
                'note' => $input->note,
                'created_by_user_id' => $user->id,
            ]);
            $run->setPeriod($input->period)->save();

            // An upload run starts empty: its file brings the employees (RunAmountImporter).
            if ($input->basis()?->usesScope() !== false) {
                $this->runScope->insertRows($run, $this->scope($input)->with(PayrollRunScope::WITH)->get());
            }
            $run->refreshTotals();

            return $run;
        });
    }

    /**
     * Employees of the scope employed in at least one day of the period. Without the visibility scope: problem()
     * already made sure the user may run payroll for this entity, and payroll must see everyone in it.
     *
     * @return Builder<Employee>
     */
    /** @return Builder<Employee> */
    private function scope(NewPayrollRun $input): Builder
    {
        return $this->runScope->employees($input->period, $input->entityId, $input->employeeCodes, $input->eligibility());
    }

    /** Regular runs, and head-wise runs of one head, are guarded against paying the same people twice. */
    private function guarded(NewPayrollRun $input): bool
    {
        return in_array($input->type, [PayrollRunType::Regular, PayrollRunType::HeadWise], true);
    }

    /**
     * Guarded runs of the same kind whose period overlaps (dates, so monthly vs half-month clash too).
     *
     * @return Builder<PayrollRun>
     */
    private function overlappingRuns(NewPayrollRun $input): Builder
    {
        return PayrollRun::query()
            ->where('run_type', $input->type->value)
            ->when($input->type->needsHead(), fn (Builder $q) => $q->where('salary_head_id', $input->salaryHeadId))
            ->whereDate('period_start', '<=', $input->period->end())
            ->whereDate('period_end', '>=', $input->period->start());
    }

    /** A run of the same kind and overlapping period for the same entity, or where either side is the whole company. */
    private function clashingRun(NewPayrollRun $input): ?PayrollRun
    {
        // Only regular runs: head-wise runs of one head may be split by eligibility (Eid bonus for some, Puja bonus
        // for others, same head and month); paying someone twice is stopped per employee ($taken in problem()).
        if ($input->type !== PayrollRunType::Regular) {
            return null;
        }

        return $this->overlappingRuns($input)
            ->when($input->entityId !== null, fn (Builder $q) => $q->where(fn (Builder $q) => $q->whereNull('entity_id')->orWhere('entity_id', $input->entityId)))
            ->with('entity:id,name')
            ->first();
    }

    /** "PR-2026-12-01": R regular, H head-wise, O off-cycle, F final settlement; then a number within the month. */
    private function nextCode(NewPayrollRun $input): string
    {
        $prefix = sprintf('P%s-%04d-%02d-', match ($input->type) {
            PayrollRunType::Regular => 'R',
            PayrollRunType::HeadWise => 'H',
            PayrollRunType::OffCycle => 'O',
            PayrollRunType::FinalSettlement => 'F',
        }, $input->period->year, $input->period->month);

        $last = PayrollRun::where('code', 'like', $prefix.'%')->pluck('code')
            ->map(fn (string $code) => (int) substr($code, strlen($prefix)))->max() ?? 0;

        return $prefix.str_pad((string) ($last + 1), 2, '0', STR_PAD_LEFT);
    }
}
