<?php

namespace App\Modules\Hrm\Actions;

use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\EmployeeOrgAssignment;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Moves an employee to another org unit from a date, keeping the history. [EMP-04]
 * The ONLY place that changes employee_org_assignments and employees.org_unit_id.
 *
 *     $action = app(ChangeOrgUnit::class);
 *     if ($problem = $action->problem($employee, $unitId, $from)) { ...show $problem['message'] under $problem['field']... }
 *     $action->handle($employee, $unitId, $from, 'Transfer');
 *
 *     $action->undoLast($employee);   removes the newest row and reopens the one before it
 *
 * What handle() does, in one transaction:
 *   1. closes the open row the day before $from      (Finance: 2024-01-01 → 2026-02-28)
 *   2. adds the new open row                          (Sales:   2026-03-01 → …)
 *   3. copies the unit to employees.org_unit_id
 *
 * Rules: the date may not be in the future (that needs a daily job: Phase 13 transfers), not before
 * joining, and must be after the newest row's start (history is only added to at the end).
 */
class ChangeOrgUnit
{
    /**
     * Why this change is not allowed, or null if it is fine.
     * field = which input the message belongs to ('org_unit_id' or 'effective_from').
     *
     * @return array{field: string, message: string}|null
     */
    public function problem(Employee $employee, int $orgUnitId, DateTimeInterface|string $from): ?array
    {
        $from = Carbon::parse($from)->startOfDay();
        $latest = $employee->orgAssignments()->first();

        $message = match (true) {
            $from->isFuture() => __('The date cannot be in the future.'),
            $from->lt($employee->join_date) => __('The date cannot be before the join date (:date).', ['date' => format_date($employee->join_date)]),
            $latest && $from->lte($latest->effective_from) => __('The date must be after :date, when the current org unit started.', ['date' => format_date($latest->effective_from)]),
            default => null,
        };
        if ($message !== null) {
            return ['field' => 'effective_from', 'message' => $message];
        }

        return $employee->org_unit_id === $orgUnitId
            ? ['field' => 'org_unit_id', 'message' => __('The employee is already in this org unit.')]
            : null;
    }

    public function handle(Employee $employee, int $orgUnitId, DateTimeInterface|string $from, ?string $reason = null): EmployeeOrgAssignment
    {
        if ($problem = $this->problem($employee, $orgUnitId, $from)) {
            throw new LogicException($problem['message']);   // callers should have checked problem() first
        }
        $from = Carbon::parse($from)->startOfDay();

        return DB::connection($employee->getConnectionName())->transaction(function () use ($employee, $orgUnitId, $from, $reason) {
            $employee->orgAssignments()->whereNull('effective_to')->first()?->close($from->copy()->subDay());

            $row = $employee->orgAssignments()->create([
                'org_unit_id' => $orgUnitId,
                'effective_from' => $from->toDateString(),
                'reason' => $reason,
            ]);

            $employee->update(['org_unit_id' => $orgUnitId]);

            return $row;
        });
    }

    /** Why the newest row cannot be undone, or null. The first row (from joining) always stays. */
    public function undoProblem(Employee $employee): ?string
    {
        return $employee->orgAssignments()->count() < 2 ? __('The first org unit cannot be undone.') : null;
    }

    /** Deletes the newest row and makes the one before it current again. Returns that one. */
    public function undoLast(Employee $employee): EmployeeOrgAssignment
    {
        if ($message = $this->undoProblem($employee)) {
            throw new LogicException($message);
        }

        return DB::connection($employee->getConnectionName())->transaction(function () use ($employee) {
            [$latest, $previous] = $employee->orgAssignments()->take(2)->get()->all();

            $latest->delete();
            $previous->update(['effective_to' => null]);
            $employee->update(['org_unit_id' => $previous->org_unit_id]);

            return $previous;
        });
    }
}
