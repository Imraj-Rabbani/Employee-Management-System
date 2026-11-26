<?php

namespace App\Modules\Hrm\Livewire\Ess;

use App\Modules\Hrm\Actions\SubmitScoreSheet;
use App\Modules\Hrm\Enums\InterviewStatus;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\InterviewPanelist;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Self Service → My Interviews: the interviews this employee is on the panel of, newest first, with
 * the applicant's CV and their own score sheet (InterviewScoreSheet: score and "Save and close").
 *
 * Sending: tick one or more sheets → "Send scores" (toolbar) → each ticked sheet that is ready is sent
 * (Hrm\Actions\SubmitScoreSheet); the others stay, and the message says why (e.g. "Not scored: Communication").
 * Only sheets that can still be sent (not sent, interview planned) have a tick box.
 *
 * No permission needed: only the user's OWN panel seats are listed (via their linked employee record).
 */
class MyInterviews extends Component
{
    /** @var list<string> ticked panelist ids */
    public array $selected = [];

    /** A score sheet was saved / sent: redraw the "My sheet" column. */
    #[On('interview-saved')]
    public function refreshList(): void {}

    public function sendSelected(SubmitScoreSheet $submit): void
    {
        $employee = $this->employee();
        $seats = $employee === null ? collect() : InterviewPanelist::query()
            ->where('employee_id', $employee->id)   // only your own sheets, whatever ids were sent
            ->whereKey($this->selected)
            ->with(['interview.application.applicant'])
            ->get();

        $sent = 0;
        $problems = [];
        foreach ($seats as $seat) {
            if ($problem = $submit->problem($seat)) {
                $problems[] = $seat->interview->application->applicant->name.': '.$problem;

                continue;
            }
            $submit->handle($seat);
            $sent++;
        }

        $this->selected = [];
        $this->dispatch('interview-saved');
        $this->dispatch('toast',
            message: implode(' ', array_filter([
                $sent > 0 ? trans_choice(':count score sheet sent. Thank you.|:count score sheets sent. Thank you.', $sent) : null,
                ...$problems,
            ])),
            variant: $problems === [] ? 'success' : ($sent > 0 ? 'warning' : 'error'),
        );
    }

    private function employee(): ?Employee
    {
        return Employee::query()->where('user_id', Auth::id())->first();
    }

    public function render(SubmitScoreSheet $submit): View
    {
        $employee = $this->employee();

        $seats = $employee === null ? collect() : InterviewPanelist::query()
            ->where('employee_id', $employee->id)
            ->with(['scores', 'interview.stage', 'interview.application.applicant', 'interview.application.jobPosting'])
            ->get()
            ->sortByDesc(fn (InterviewPanelist $p) => $p->interview->scheduled_at)
            ->values();

        // Tick box only where sending is still possible; a draft that is not ready says what is missing.
        $sendable = $seats->filter(fn (InterviewPanelist $p) => ! $p->isSubmitted() && $p->interview->status === InterviewStatus::Scheduled);
        $missing = $sendable->filter(fn (InterviewPanelist $p) => $p->scores->isNotEmpty())
            ->mapWithKeys(fn (InterviewPanelist $p) => [$p->id => $submit->problem($p)])->filter();

        return view('hrm::livewire.ess.my-interviews', [
            'employee' => $employee,
            'seats' => $seats,
            'sendableIds' => $sendable->map(fn (InterviewPanelist $p) => (string) $p->id)->values(),
            'missing' => $missing,
        ]);
    }
}
