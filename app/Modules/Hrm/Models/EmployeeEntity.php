<?php

namespace App\Modules\Hrm\Models;

use App\Core\Concerns\HasEffectiveDates;
use App\Core\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One period of an employee in one entity (legacy `entityitems`): "Department = Finance from 2024-01-01
 * to 2026-02-28". One OPEN row (effective_to NULL) per employee and entity type.
 * Do not create or change these directly: use Hrm\Actions\ChangeEntity.
 *
 * @property int $id
 * @property int $employee_id
 * @property int $entity_type_id
 * @property int $entity_id
 * @property string|null $reason
 */
class EmployeeEntity extends TenantModel
{
    use HasEffectiveDates;

    protected $fillable = ['employee_id', 'entity_type_id', 'entity_id', 'effective_from', 'effective_to', 'reason'];

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return BelongsTo<Entity, $this> */
    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    /** @return BelongsTo<EntityType, $this> */
    public function type(): BelongsTo
    {
        return $this->belongsTo(EntityType::class, 'entity_type_id');
    }
}
