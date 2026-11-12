<?php

namespace App\Modules\Hrm\Models;

use App\Core\Models\TenantModel;
use App\Modules\Hrm\Enums\LeaveEntryType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * One change of a leave balance (table `leave_ledger`). [LV-03]
 * Written ONLY by App\Modules\Hrm\Services\LeaveLedger, never edited or deleted.
 *
 * @property int $id
 * @property int $employee_id
 * @property int $leave_type_id
 * @property int $year
 * @property LeaveEntryType $entry_type
 * @property float $days
 * @property Carbon $entry_date
 * @property Carbon|null $expires_on
 * @property string|null $source_type
 * @property int|null $source_id
 * @property string|null $note
 * @property int|null $created_by_user_id
 */
class LeaveLedgerEntry extends TenantModel
{
    protected $table = 'leave_ledger';

    /**
     * No activity log entries (Spatie's LogsActivity records only the events listed here). The ledger IS the history: no activity log entries on top.
     *
     * @var list<string>
     */
    protected static $recordEvents = [];

    protected $fillable = [
        'employee_id', 'leave_type_id', 'year', 'entry_type', 'days', 'entry_date', 'expires_on',
        'source_type', 'source_id', 'note', 'created_by_user_id',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'entry_type' => LeaveEntryType::class,
            'days' => 'float',
            'entry_date' => 'date',
            'expires_on' => 'date',
        ];
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

    /** @return MorphTo<Model, $this> */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
