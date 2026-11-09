<?php

namespace App\Modules\Hrm\Livewire\Ess;

use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\InterviewPanelist;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Self Service → My Interviews: the interviews this employee is on the panel of, newest first, with
 * the applicant's CV and their own score sheet (InterviewScoreSheet). [REC-05]
 * No permission needed: only the user's OWN panel seats are listed (via their linked employee record).
 */
class MyInterviews extends Component
{
    /** A score sheet was saved / sent: redraw the "My sheet" column. */
    #[On('interview-saved')]
    public function refreshList(): void {}

    public function render(): View
    {
        $employee = Employee::query()->where('user_id', Auth::id())->first();

        $seats = $employee === null ? collect() : InterviewPanelist::query()
            ->where('employee_id', $employee->id)
            ->with(['scores', 'interview.application.applicant', 'interview.application.jobPosting'])
            ->get()
            ->sortByDesc(fn (InterviewPanelist $p) => $p->interview->scheduled_at)
            ->values();

        return view('hrm::livewire.ess.my-interviews', ['employee' => $employee, 'seats' => $seats]);
    }
}
