<?php

namespace App\Modules\Hrm\Livewire\Recruitment;

use App\Modules\Hrm\Enums\InterviewStatus;
use App\Modules\Hrm\Models\Interview;
use App\Modules\Hrm\Models\JobPosting;
use App\Modules\Hrm\Services\InterviewScoring;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * A job posting's interviews and ranking (below the pipeline). [REC-05]
 *
 *   Interviews  every round of this posting's applicants: when, where, the panel (✓ = scores sent; click a
 *               name to open / enter that sheet), the round's result, and Change / Complete / Cancel.
 *   Ranking     InterviewScoring::ranking(): applicants by the average of their COMPLETED rounds.
 *
 * Permission: the posting page (hrm.recruitment.manage, re-checked on every click); the posting through
 * visibleTo(), and an interview always THROUGH the posting.
 */
class PostingInterviews extends Component
{
    #[Locked]
    public int $postingId;

    public function complete(int $interviewId): void
    {
        $interview = $this->interview($interviewId);

        if ($problem = $interview->completeProblem()) {
            $this->dispatch('toast', message: $problem, variant: 'danger');

            return;
        }

        $interview->complete();
        $this->dispatch('toast', message: __(':round completed: its scores now count in the ranking.', ['round' => $interview->title()]), variant: 'success');
    }

    public function cancel(int $interviewId): void
    {
        $interview = $this->interview($interviewId);

        if ($interview->status !== InterviewStatus::Scheduled) {
            $this->dispatch('toast', message: __('Only a planned interview can be cancelled.'), variant: 'danger');

            return;
        }

        $interview->cancel();
        $this->dispatch('interview-saved');   // the pipeline shows the "Schedule" button again
        $this->dispatch('toast', message: __(':round cancelled.', ['round' => $interview->title()]), variant: 'success');
    }

    /** Scheduled / changed elsewhere, or a score sheet was saved: draw again. */
    #[On('interview-saved')]
    public function refreshInterviews(): void {}

    private function posting(): JobPosting
    {
        return JobPosting::query()->visibleTo(Auth::user())->findOrFail($this->postingId);
    }

    private function interview(int $id): Interview
    {
        return Interview::query()
            ->whereHas('application', fn ($q) => $q->where('job_posting_id', $this->posting()->id))
            ->findOrFail($id);
    }

    public function render(InterviewScoring $scoring): View
    {
        $posting = $this->posting();

        $interviews = Interview::query()
            ->whereHas('application', fn ($q) => $q->where('job_posting_id', $posting->id))
            ->with(['application.applicant', 'panelists.employee', 'panelists.scores'])
            ->orderByRaw("status = 'scheduled' desc")   // planned ones first
            ->orderByDesc('scheduled_at')
            ->get();

        return view('hrm::livewire.recruitment.posting-interviews', [
            'interviews' => $interviews,
            'results' => $interviews->mapWithKeys(fn (Interview $i) => [$i->id => $scoring->interview($i)]),
            'ranking' => $scoring->ranking($posting),
        ]);
    }
}
