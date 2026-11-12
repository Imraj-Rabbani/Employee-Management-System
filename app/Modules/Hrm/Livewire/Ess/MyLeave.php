<?php

namespace App\Modules\Hrm\Livewire\Ess;

use App\Modules\Hrm\Data\LeaveYear;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\LeaveApplication;
use App\Modules\Hrm\Models\LeaveBalance;
use App\Modules\Hrm\Models\LeaveType;
use App\Modules\Hrm\Scopes\VisibleToCurrentUser;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Self Service → My Leave Requests (8.4): my balances this leave year, "Apply for leave", and my last
 * applications (click one: details, change and send again, withdraw). [LV-04, ESS-02]
 * The forms are LeaveApplicationForm / LeaveApplicationDetails with self = true.
 */
class MyLeave extends Component
{
    /** @var array<string, string> */
    protected $listeners = ['leave-saved' => '$refresh'];

    public function render(): View
    {
        $employee = Employee::query()->withoutGlobalScope(VisibleToCurrentUser::class)->with('leaveProfile.items')->where('user_id', Auth::id())->first();
        $year = LeaveYear::current();

        $profileTypeIds = $employee?->leaveProfile?->items->pluck('leave_type_id')->all() ?? [];

        return view('hrm::livewire.ess.my-leave', [
            'employee' => $employee,
            'year' => $year,
            'types' => LeaveType::query()->whereIn('id', $profileTypeIds)->where('is_active', true)->ordered()->get(),
            'balances' => $employee
                ? LeaveBalance::query()->where('employee_id', $employee->id)->where('year', $year->year)->get()->keyBy('leave_type_id')
                : collect(),
            'applications' => $employee
                ? LeaveApplication::query()->where('employee_id', $employee->id)->with(['leaveType:id,name,code', 'approvals'])->latest('id')->limit(20)->get()
                : collect(),
        ]);
    }
}
