<?php

namespace App\Modules\Hrm\Models;

use App\Core\Concerns\HasEffectiveDates;
use App\Core\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One period in an employee's org unit history. [EMP-04]
 * Do not create or change these directly: use ChangeOrgUnit, which also keeps employees.org_unit_id in step.
 *
 * @property int $id
 * @property int $employee_id
 * @property int $org_unit_id
 * @property string|null $reason
 */
class EmployeeOrgAssignment extends TenantModel
{
    use HasEffectiveDates;

    protected $fillable = ['employee_id', 'org_unit_id', 'effective_from', 'effective_to', 'reason'];

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return BelongsTo<OrgUnit, $this> */
    public function orgUnit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class);
    }
}
