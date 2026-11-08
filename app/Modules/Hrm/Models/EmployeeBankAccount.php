<?php

namespace App\Modules\Hrm\Models;

use App\Core\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A bank account of an employee. The primary one receives the salary (Phase 12). [EMP-01]
 *
 * @property int $id
 * @property int $employee_id
 * @property string $bank_name
 * @property string|null $branch_name
 * @property string|null $routing_no
 * @property string|null $account_name
 * @property string $account_no
 * @property bool $is_primary
 */
class EmployeeBankAccount extends TenantModel
{
    protected $fillable = ['bank_name', 'branch_name', 'routing_no', 'account_name', 'account_no', 'is_primary'];

    protected $attributes = ['is_primary' => false];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
