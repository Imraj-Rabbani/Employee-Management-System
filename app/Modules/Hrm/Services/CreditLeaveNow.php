<?php

namespace App\Modules\Hrm\Services;

use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\LeaveProfile;
use App\Modules\Hrm\Models\LeaveProfileItem;
use App\Modules\Hrm\Models\LeaveType;
use App\Modules\Hrm\Scopes\VisibleToCurrentUser;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Credits leave that is due TODAY at once, instead of waiting for the nightly hrm:accrue-leave.
 * Same work as the nightly job (AccrueEarnedLeave), only for the employees affected by a change:
 *
 *     forEmployees([5])      an employee got a leave profile, or a new one / new join date (Employee::booted)
 *     forProfile($profile)   a profile's leave types or days changed (LeaveProfile::syncEntitlements)
 *     forType($type)         a leave type was switched on or its earning rule changed (LeaveType::booted)
 *
 * Only what is due by today is credited: a Fixed type gives the year's days now, a monthly one the months
 * already started / finished. Days credited earlier are never changed (they are history in the ledger).
 *
 * Runs after the surrounding transaction commits. A failure here never stops the save: it is reported
 * and the nightly job tries again.
 */
class CreditLeaveNow
{
    public function __construct(private AccrueEarnedLeave $accrual) {}

    /** @param  list<int>  $employeeIds */
    public function forEmployees(array $employeeIds): void
    {
        if ($employeeIds === []) {
            return;
        }

        // afterCommit: inside a transaction, wait until it is saved for good; outside one, run now.
        DB::connection('tenant')->afterCommit(function () use ($employeeIds) {
            try {
                $this->accrual->run(AccrueEarnedLeave::today(), $employeeIds);
            } catch (Throwable $e) {
                report($e);
            }
        });
    }

    public function forProfile(LeaveProfile $profile): void
    {
        $this->forEmployees($this->employeesWithProfiles([$profile->id]));
    }

    public function forType(LeaveType $type): void
    {
        $profileIds = LeaveProfileItem::query()->where('leave_type_id', $type->id)->pluck('leave_profile_id')->all();

        $this->forEmployees($this->employeesWithProfiles($profileIds));
    }

    /**
     * withoutGlobalScope: everyone with the profile, not only the employees the logged-in user may see.
     *
     * @param  list<int>  $profileIds
     * @return list<int>
     */
    private function employeesWithProfiles(array $profileIds): array
    {
        if ($profileIds === []) {
            return [];
        }

        return Employee::query()->withoutGlobalScope(VisibleToCurrentUser::class)
            ->whereIn('leave_profile_id', $profileIds)
            ->pluck('id')->map(fn ($id) => (int) $id)->all();
    }
}
