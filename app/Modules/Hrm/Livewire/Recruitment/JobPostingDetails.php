<?php

namespace App\Modules\Hrm\Livewire\Recruitment;

use App\Modules\Hrm\Enums\JobPostingStatus;
use App\Modules\Hrm\Models\JobPosting;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The body of one job posting's page: status, details, and the buttons Edit / Open / Close / Reopen. [REC-02]
 * The rules live in the model (JobPosting::openProblem(), open(), close()); this class only asks and reports.
 * Permission: the page route needs hrm.recruitment.manage, and Livewire re-checks it on every click.
 */
class JobPostingDetails extends Component
{
    #[Locked]
    public int $postingId;

    /** Draft → Open, or Closed → Open again (Reopen). */
    public function open(): void
    {
        $posting = $this->posting();

        if (($problem = $posting->openProblem()) !== null) {
            $this->dispatch('toast', message: $problem, variant: 'danger');

            return;
        }

        $posting->open();
        $this->dispatch('job-posting-saved');
        $this->dispatch('toast', message: __('Posting :no is open.', ['no' => $posting->number()]), variant: 'success');
    }

    public function close(): void
    {
        $posting = $this->posting();

        if ($posting->status !== JobPostingStatus::Open) {
            $this->dispatch('toast', message: __('Only an open posting can be closed.'), variant: 'danger');

            return;
        }

        $posting->close();
        $this->dispatch('job-posting-saved');
        $this->dispatch('toast', message: __('Posting :no is closed.', ['no' => $posting->number()]), variant: 'success');
    }

    /** The edit modal saved: draw again with the new values. */
    #[On('job-posting-saved')]
    public function refreshDetails(): void {}

    private function posting(): JobPosting
    {
        return JobPosting::query()->visibleTo(Auth::user())
            ->with(['manpowerRequest.orgUnit', 'manpowerRequest.designation', 'manpowerRequest.grade'])
            ->findOrFail($this->postingId);
    }

    public function render(): View
    {
        return view('hrm::livewire.recruitment.job-posting-details', ['posting' => $this->posting()]);
    }
}
