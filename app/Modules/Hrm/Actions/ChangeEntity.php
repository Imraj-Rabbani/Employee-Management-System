<?php

namespace App\Modules\Hrm\Actions;

use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\EmployeeEntity;
use App\Modules\Hrm\Models\Entity;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Puts an employee in an entity from a date, keeping the history (legacy `entityitems`).
 * The ONLY place that writes employee_entities (test factories aside).
 *
 * An employee has one entity PER TYPE, and each type has its own history. Moving someone from
 * Department Finance to Department Sales does not touch their Branch.
 *
 *     $action = app(ChangeEntity::class);
 *     $action->assignOnJoining($employee, [$financeId, $gulshanId]);   new employee: rows from the join date
 *
 *     if ($problem = $action->problem($employee, $salesId, $from)) { ...show $problem['message'] under $problem['field']... }
 *     $action->handle($employee, $salesId, $from, 'Transfer');
 *
 *     $action->undoLast($employee, $departmentTypeId);   removes that type's newest row, reopens the one before
 *
 * What handle() does, in one transaction:
 *   1. closes the open row OF THE SAME TYPE the day before $from   (Finance: 2024-01-01 → 2026-02-28)
 *   2. adds the new open row                                        (Sales:   2026-03-01 → …)
 *
 * Rules: the date may not be in the future (that needs a daily job: future transfers), not before
 * joining, and must be after the start of that type's newest row (history is only added to at the end).
 */
class ChangeEntity
{
    /**
     * Rows for a NEW employee: one per given entity, from the join date. Two entities of the same type
     * are refused (one entity per type). Empty values are skipped, so a form can pass every dropdown.
     *
     * @param  array<int|string, int|string|null>  $entityIds
     */
    public function assignOnJoining(Employee $employee, array $entityIds): void
    {
        $entities = Entity::whereIn('id', array_filter($entityIds))->get();
        if ($entities->pluck('entity_type_id')->duplicates()->isNotEmpty()) {
            throw new LogicException(__('An employee can be in only one entity of each type.'));
        }

        foreach ($entities as $entity) {
            if ($employee->currentEntityAssignments()->where('entity_type_id', $entity->entity_type_id)->exists()) {
                continue;   // already placed (e.g. called twice): never two open rows of one type
            }
            $employee->entityAssignments()->create([
                'entity_type_id' => $entity->entity_type_id,
                'entity_id' => $entity->id,
                'effective_from' => $employee->join_date->toDateString(),
            ]);
        }
    }

    /**
     * Why this change is not allowed, or null if it is fine.
     * field = which input the message belongs to ('entity_id' or 'effective_from').
     *
     * @return array{field: string, message: string}|null
     */
    public function problem(Employee $employee, int $entityId, DateTimeInterface|string $from): ?array
    {
        $entity = Entity::find($entityId);
        if ($entity === null) {
            return ['field' => 'entity_id', 'message' => __('This entity does not exist.')];
        }

        $from = Carbon::parse($from)->startOfDay();
        $latest = $this->rowsOfType($employee, $entity->entity_type_id)->first();

        $message = match (true) {
            $from->isFuture() => __('The date cannot be in the future.'),
            $from->lt($employee->join_date) => __('The date cannot be before the join date (:date).', ['date' => format_date($employee->join_date)]),
            $latest && $from->lte($latest->effective_from) => __('The date must be after :date, when the current :type started.', [
                'date' => format_date($latest->effective_from), 'type' => $entity->type->name,
            ]),
            default => null,
        };
        if ($message !== null) {
            return ['field' => 'effective_from', 'message' => $message];
        }

        return $latest?->isOpen() && $latest->entity_id === $entityId
            ? ['field' => 'entity_id', 'message' => __('The employee is already in this entity.')]
            : null;
    }

    public function handle(Employee $employee, int $entityId, DateTimeInterface|string $from, ?string $reason = null): EmployeeEntity
    {
        if ($problem = $this->problem($employee, $entityId, $from)) {
            throw new LogicException($problem['message']);   // callers should have checked problem() first
        }
        $from = Carbon::parse($from)->startOfDay();
        $entity = Entity::findOrFail($entityId);

        return DB::connection($employee->getConnectionName())->transaction(function () use ($employee, $entity, $from, $reason) {
            $this->rowsOfType($employee, $entity->entity_type_id)->whereNull('effective_to')->first()
                ?->close($from->copy()->subDay());

            return $employee->entityAssignments()->create([
                'entity_type_id' => $entity->entity_type_id,
                'entity_id' => $entity->id,
                'effective_from' => $from->toDateString(),
                'reason' => $reason,
            ]);
        });
    }

    /** Why the newest row of this type cannot be undone, or null. A type's first row always stays. */
    public function undoProblem(Employee $employee, int $typeId): ?string
    {
        return $this->rowsOfType($employee, $typeId)->count() < 2 ? __('The first entity of a type cannot be undone.') : null;
    }

    /** Deletes the newest row of this type and makes the one before it current again. Returns that one. */
    public function undoLast(Employee $employee, int $typeId): EmployeeEntity
    {
        if ($message = $this->undoProblem($employee, $typeId)) {
            throw new LogicException($message);
        }

        return DB::connection($employee->getConnectionName())->transaction(function () use ($employee, $typeId) {
            [$latest, $previous] = $this->rowsOfType($employee, $typeId)->take(2)->get()->all();

            $latest->delete();
            $previous->update(['effective_to' => null]);

            return $previous;
        });
    }

    /** @return HasMany<EmployeeEntity, Employee> newest first */
    private function rowsOfType(Employee $employee, int $typeId)
    {
        return $employee->entityAssignments()->where('entity_type_id', $typeId);
    }
}
