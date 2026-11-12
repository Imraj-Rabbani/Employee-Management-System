<?php

namespace App\Modules\Hrm\Models;

use App\Core\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One counted day of a leave application (1 or 0.5). Written with the application by ApplyForLeave;
 * no own history (the application is logged). [LV-04]
 *
 * @property int $id
 * @property int $leave_application_id
 * @property int $employee_id
 * @property Carbon $date
 * @property float $days
 */
class LeaveApplicationDay extends TenantModel
{
    public $timestamps = false;

    protected static $recordEvents = [];

    protected $fillable = ['leave_application_id', 'employee_id', 'date', 'days'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['date' => 'date', 'days' => 'float'];
    }

    /** @return BelongsTo<LeaveApplication, $this> */
    public function application(): BelongsTo
    {
        return $this->belongsTo(LeaveApplication::class, 'leave_application_id');
    }
}
