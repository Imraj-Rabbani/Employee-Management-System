<?php

namespace App\Modules\Payroll\Livewire\Ess;

use App\Modules\Hrm\Models\Employee;
use App\Modules\Payroll\Models\OvertimeRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Self Service → My Attendance → My overtime (7.7): my last requests with asked / approved hours and status,
 * "Ask for overtime", and "Change and send again" for one that was sent back. [TA-07, ESS-02]
 * The form is OvertimeForm with self = true (own employee record only).
 */
class MyOvertime extends Component
{
    /** @var array<string, string> */
    protected $listeners = ['overtime-saved' => '$refresh'];

    public function render(): View
    {
        $employee = Employee::query()->where('user_id', Auth::id())->first();

        return view('payroll::livewire.ess.my-overtime', [
            'employee' => $employee,
            'requests' => $employee
                ? OvertimeRequest::query()->where('employee_id', $employee->id)->with('approvals')->latest('id')->limit(20)->get()
                : collect(),
        ]);
    }
}
