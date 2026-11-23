<?php

namespace App\Modules\Payroll\Services;

use App\Core\Support\Period;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\Entity;
use App\Modules\Hrm\Scopes\VisibleToCurrentUser;
use App\Modules\Payroll\Enums\PayrollRunType;
use App\Modules\Payroll\Models\PayrollAdjustment;
use App\Modules\Payroll\Models\PayrollRun;
use App\Modules\Payroll\Models\PayrollRunEmployee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Who is in a run, and what the run remembers about them (the snapshot on payroll_run_employees).
 *
 *     $scope->employees($period, $entityId, $codes)   employees of an entity (or everyone) employed on a day of the period
 *     $scope->snapshot($run, $employee)               entity, primary bank account, hold flags, leaving, period days
 *     $scope->refresh($row, $employee)                the same, written to an existing row (every calculation does it)
 *     $scope->sync($run)                              calculating the whole run again: joiners in, people out of scope out
 *
 * $employee needs bankAccounts and entities.type loaded (Employee::with(PayrollRunScope::WITH)).
 */
class PayrollRunScope
{
    /** @var list<string> what snapshot() reads */
    public const WITH = ['bankAccounts', 'entities.type'];

    /**
     * @param  list<string>  $codes  [] = everyone in the scope
     * @param  array{religions?: list<int>, confirmed_only?: bool, joined_before?: \DateTimeInterface|null}  $eligibility  head-wise runs
     * @return Builder<Employee>
     */
    public function employees(Period $period, ?int $entityId, array $codes = [], array $eligibility = []): Builder
    {
        $religions = $eligibility['religions'] ?? [];
        $joinedBefore = $eligibility['joined_before'] ?? null;

        return Employee::withoutGlobalScope(VisibleToCurrentUser::class)
            ->whereDate('join_date', '<=', $period->end())
            ->where(fn (Builder $q) => $q->whereNull('separation_date')->orWhereDate('separation_date', '>=', $period->start()))
            ->when($entityId !== null, fn (Builder $q) => $q->inEntities([$entityId]))
            ->when($codes !== [], fn (Builder $q) => $q->whereIn('code', $codes))
            ->when($religions !== [], fn (Builder $q) => $q->whereIn('religion', $religions))
            ->when($eligibility['confirmed_only'] ?? false, fn (Builder $q) => $q->whereNotNull('confirmation_date')->whereDate('confirmation_date', '<=', $period->end()))
            ->when($joinedBefore !== null, fn (Builder $q) => $q->whereDate('join_date', '<', $joinedBefore))
            ->orderBy('code');
    }

    /** @return array{religions: list<int>, confirmed_only: bool, joined_before: ?\DateTimeInterface} the run's head-wise rules */
    public function eligibilityOf(PayrollRun $run): array
    {
        return [
            'religions' => array_map('intval', $run->religions ?? []),
            'confirmed_only' => $run->confirmed_only,
            'joined_before' => $run->joined_before,
        ];
    }

    /** @return array<string, mixed> the snapshot columns of payroll_run_employees */
    public function snapshot(PayrollRun $run, Employee $employee): array
    {
        $period = $run->period();

        return [
            // Whole-company run: the employee's entity of the first type (e.g. Department), for grouping and reports.
            'entity_id' => $run->entity_id ?? $employee->entities->sortBy(fn (Entity $e) => [$e->type->sequence, $e->type->name])->first()?->id,
            'bank_account_id' => $employee->bankAccounts->firstWhere('is_primary', true)?->id,
            'period_days' => $period->days(),
            'is_on_hold' => (bool) $employee->salary_on_hold,
            'hold_bank_transfer' => (bool) $employee->bank_transfer_on_hold,
            'is_separated' => $employee->separation_date !== null && $period->contains($employee->separation_date->toDateString()),
        ];
    }

    public function refresh(PayrollRunEmployee $row, Employee $employee): void
    {
        $row->forceFill($this->snapshot($row->run, $employee))->save();
    }

    /**
     * Brings the run's employees up to date before the whole run is calculated again (draft / processed only):
     *   - out: employees no longer employed in the period or no longer in the run's entity (their items go too, the
     *     adjustments the run took are freed)
     *   - in:  employees now in scope who are not in it yet (not for a run limited to a list of codes), unless another
     *     run of the same kind already pays them for an overlapping period
     *
     * @return array{added: int, removed: int}
     */
    public function sync(PayrollRun $run): array
    {
        $current = $run->employees()->pluck('employee_id')->all();
        if ($run->isUploadRun()) {
            // The upload says who: still employed in the period and in the run's entity.
            $inScope = $this->employees($run->period(), $run->entity_id)->whereIn('employees.id', $run->amounts()->select('employee_id'))->pluck('employees.id')->all();
        } else {
            $codes = $run->limited_to_list
                ? Employee::withoutGlobalScopes()->whereIn('id', $current)->pluck('code')->all()
                : [];
            $inScope = $this->employees($run->period(), $run->entity_id, $codes, $this->eligibilityOf($run))->pluck('employees.id')->all();
        }

        $out = array_values(array_diff($current, $inScope));
        if ($out !== []) {
            PayrollAdjustment::query()->where('payroll_run_id', $run->id)->whereIn('employee_id', $out)->update(['payroll_run_id' => null]);
            $run->employees()->whereIn('employee_id', $out)->delete();   // items and arrears cascade
        }

        $added = 0;
        if (! $run->limited_to_list) {
            $new = array_values(array_diff($inScope, $current, $this->paidElsewhere($run)));
            if ($new !== []) {
                $employees = Employee::withoutGlobalScopes()->whereIn('id', $new)->with(self::WITH)->get();
                $this->insertRows($run, $employees);
                $added = $employees->count();
            }
        }

        return ['added' => $added, 'removed' => count($out)];
    }

    /** @return list<int> employees in another run of the same guarded kind whose period overlaps */
    public function paidElsewhere(PayrollRun $run): array
    {
        if (! in_array($run->run_type, [PayrollRunType::Regular, PayrollRunType::HeadWise], true)) {
            return [];
        }

        return PayrollRunEmployee::query()
            ->whereIn('payroll_run_id', PayrollRun::query()
                ->whereKeyNot($run->id)
                ->where('run_type', $run->run_type->value)
                ->when($run->run_type === PayrollRunType::HeadWise, fn (Builder $q) => $q->where('salary_head_id', $run->salary_head_id))
                ->whereDate('period_start', '<=', $run->period_end)
                ->whereDate('period_end', '>=', $run->period_start)
                ->select('id'))
            ->pluck('employee_id')->all();
    }

    /** @param  Collection<int, Employee>  $employees  with self::WITH loaded */
    public function insertRows(PayrollRun $run, Collection $employees): void
    {
        $now = now();
        $rows = $employees->map(fn (Employee $e) => [
            'payroll_run_id' => $run->id, 'employee_id' => $e->id, 'calc_status' => 'pending',
            'created_at' => $now, 'updated_at' => $now, ...$this->snapshot($run, $e),
        ])->all();

        // One INSERT per 500 rows instead of one per employee: a company payroll can have thousands.
        foreach (array_chunk($rows, 500) as $chunk) {
            PayrollRunEmployee::insert($chunk);
        }
    }
}
