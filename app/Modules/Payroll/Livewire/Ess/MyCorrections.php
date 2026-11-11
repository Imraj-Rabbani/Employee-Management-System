<?php

namespace App\Modules\Payroll\Livewire\Ess;

use App\Modules\Hrm\Models\Employee;
use App\Modules\Payroll\Models\AttendanceCorrection;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Self Service → My Attendance → My corrections (7.6): my last corrections with their status and the
 * approver's comment, "Request a correction", and "Change and send again" for one that was sent back. [TA-06, ESS-02]
 * The form itself is CorrectionForm with self = true (own employee record, company limits).
 */
class MyCorrections extends Component
{
    /** @var array<string, string> */
    protected $listeners = ['corrections-saved' => '$refresh'];

    public function render(): View
    {
        $employee = Employee::query()->where('user_id', Auth::id())->first();

        return view('payroll::livewire.ess.my-corrections', [
            'employee' => $employee,
            'corrections' => $employee
                ? AttendanceCorrection::query()->where('employee_id', $employee->id)->with('approvals')->latest('id')->limit(20)->get()
                : collect(),
        ]);
    }
}
