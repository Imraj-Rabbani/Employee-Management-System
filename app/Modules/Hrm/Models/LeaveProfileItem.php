<?php

namespace App\Modules\Hrm\Models;

use App\Core\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One leave type in a leave profile, with its days per year (legacy `leaveitems`). [LV-02]
 * Written only through LeaveProfile::syncEntitlements(), which logs ONE entry on the profile.
 *
 * @property int $id
 * @property int $leave_profile_id
 * @property int $leave_type_id
 * @property float $days_per_year
 */
class LeaveProfileItem extends TenantModel
{
    /** No own history entries: the profile gets one readable entry per save. */
    protected static $recordEvents = [];

    protected $fillable = ['leave_profile_id', 'leave_type_id', 'days_per_year'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['days_per_year' => 'float'];
    }

    /** @return BelongsTo<LeaveProfile, $this> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(LeaveProfile::class, 'leave_profile_id');
    }

    /** @return BelongsTo<LeaveType, $this> */
    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }
}
