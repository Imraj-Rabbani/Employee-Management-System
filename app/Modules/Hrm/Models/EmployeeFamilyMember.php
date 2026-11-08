<?php

namespace App\Modules\Hrm\Models;

use App\Core\Models\TenantModel;
use App\Modules\Hrm\Enums\EmployeeRelation;
use App\Modules\Hrm\Enums\Gender;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A family member of an employee (father, spouse, child ...). [EMP-02]
 *
 * @property int $id
 * @property int $employee_id
 * @property EmployeeRelation|null $relation
 * @property string $name
 * @property string|null $name_local
 * @property Carbon|null $birth_date
 * @property Gender|null $gender
 * @property string|null $occupation
 * @property string|null $phone
 */
class EmployeeFamilyMember extends TenantModel
{
    protected $fillable = ['relation', 'name', 'name_local', 'birth_date', 'gender', 'occupation', 'phone'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['relation' => EmployeeRelation::class, 'gender' => Gender::class, 'birth_date' => 'date'];
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
