<?php

namespace App\Modules\Hrm\Models;

use App\Core\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One credited earning period (8.5): written only by Services\AccrueEarnedLeave. [LV-05]
 * Its Earned ledger row has this as source. No own history: the ledger is the history.
 *
 * @property int $id
 * @property int $employee_id
 * @property int $leave_type_id
 * @property int $year
 * @property Carbon $period_start
 * @property Carbon $period_end
 * @property float $days
 * @property string $basis
 * @property int|null $leave_ledger_id
 */
class LeaveAccrual extends TenantModel
{
    protected static $recordEvents = [];

    protected $fillable = ['employee_id', 'leave_type_id', 'year', 'period_start', 'period_end', 'days', 'basis', 'leave_ledger_id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['period_start' => 'date', 'period_end' => 'date', 'days' => 'float'];
    }

    /** @return BelongsTo<LeaveType, $this> */
    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    /** @return BelongsTo<LeaveLedgerEntry, $this> */
    public function ledgerEntry(): BelongsTo
    {
        return $this->belongsTo(LeaveLedgerEntry::class, 'leave_ledger_id');
    }
}
