<?php

namespace App\Modules\Hrm\Models;

use App\Core\Models\TenantModel;
use App\Modules\Hrm\Enums\EmployeeRelation;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Someone who receives a share of the employee's benefits (provident fund, gratuity, insurance)
 * if the employee dies. All nominees of one employee must add up to 100%. [EMP-02]
 *
 *     $nominee->guardian['name']     the guardian of a minor nominee (array thanks to the cast)
 *
 * @property int $id
 * @property int $employee_id
 * @property string $name
 * @property EmployeeRelation|null $relation
 * @property string|null $national_id
 * @property string|null $phone
 * @property string|null $address
 * @property string $share_percent decimal: "33.34" (a string, so no float rounding)
 * @property bool $is_minor
 * @property array{name?: string|null, national_id?: string|null, phone?: string|null, relation?: string|null}|null $guardian
 */
class EmployeeNominee extends TenantModel
{
    protected $fillable = ['name', 'relation', 'national_id', 'phone', 'address', 'share_percent', 'is_minor', 'guardian'];

    protected $attributes = ['is_minor' => false];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'relation' => EmployeeRelation::class,
            'share_percent' => 'decimal:2',
            'is_minor' => 'boolean',
            'guardian' => 'array',
        ];
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
