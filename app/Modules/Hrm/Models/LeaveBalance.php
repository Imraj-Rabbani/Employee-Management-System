<?php

namespace App\Modules\Hrm\Models;

use App\Core\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cached totals of the ledger for one employee, leave type and leave year. [LV-03]
 * Rebuilt by LeaveLedger::refresh() after every ledger write; never changed by hand.
 * `taken`, `encashed`, `expired` are positive; balance = opening + earned + carried_forward + adjusted − used.
 *
 * @property int $id
 * @property int $employee_id
 * @property int $leave_type_id
 * @property int $year
 * @property float $opening
 * @property float $earned
 * @property float $carried_forward
 * @property float $adjusted
 * @property float $taken
 * @property float $encashed
 * @property float $expired
 * @property float $balance
 */
class LeaveBalance extends TenantModel
{
    /** A cache of the ledger: nothing to log. */
    protected static $recordEvents = [];

    public const AMOUNTS = ['opening', 'earned', 'carried_forward', 'adjusted', 'taken', 'encashed', 'expired', 'balance'];

    protected $fillable = ['employee_id', 'leave_type_id', 'year', ...self::AMOUNTS];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return array_fill_keys(self::AMOUNTS, 'float');
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return BelongsTo<LeaveType, $this> */
    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }
}
