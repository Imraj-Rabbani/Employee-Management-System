<?php

namespace App\Modules\Hrm\Livewire\Recruitment;

use App\Modules\Hrm\Actions\ApplyToPosting;
use App\Modules\Hrm\Models\Applicant;
use App\Modules\Hrm\Models\Application;
use App\Modules\Hrm\Models\JobPosting;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * One applicant's page: header (contact, CV, Edit), personal details, and their applications with
 * "Apply to posting". Education and work history are separate cards (ApplicantEducations / ...WorkHistories). [REC-03]
 *
 * Applications are listed only for postings the user can see (Application::visibleTo); one to a posting
 * outside the user's org units is counted ("+1 in other org units") but not shown.
 */
class ApplicantProfile extends Component
{
    #[Locked]
    public int $applicantId;

    /** The posting chosen in the "Apply to posting" modal. */
    public int|string $postingId = '';

    public function openApply(): void
    {
        $this->postingId = '';
        $this->resetValidation();
        Flux::modal('applicant-apply')->show();
    }

    public function apply(ApplyToPosting $action): void
    {
        $applicant = $this->applicant();
        $this->validate(['postingId' => ['required', Rule::in(array_keys($this->postingOptions($applicant)))]], [], ['postingId' => __('job posting')]);

        $posting = JobPosting::findOrFail((int) $this->postingId);
        if ($problem = $action->problem($applicant, $posting)) {
            $this->addError('postingId', $problem);

            return;
        }

        $action->handle($applicant, $posting, Auth::id());

        Flux::modal('applicant-apply')->close();
        $this->dispatch('toast', message: __(':name applied to :no.', ['name' => $applicant->name, 'no' => $posting->number()]), variant: 'success');
    }

    /** The edit modal saved: draw again. */
    #[On('applicant-saved')]
    public function refreshProfile(): void {}

    private function applicant(): Applicant
    {
        return Applicant::findOrFail($this->applicantId);
    }

    /**
     * Postings this person can still apply to: visible, taking applications today, not applied to yet.
     *
     * @return array<int, string>
     */
    private function postingOptions(Applicant $applicant): array
    {
        return JobPosting::query()->visibleTo(Auth::user())->where('status', 'open')
            ->whereDoesntHave('applications', fn ($q) => $q->where('applicant_id', $applicant->id))
            ->with('manpowerRequest.orgUnit')->orderByDesc('id')->get()
            ->filter(fn (JobPosting $p) => $p->isAcceptingApplications())
            ->mapWithKeys(fn (JobPosting $p) => [$p->id => $p->number().' · '.$p->title.' ('.($p->manpowerRequest->orgUnit->name ?? '?').')'])
            ->all();
    }

    public function render(): View
    {
        $applicant = $this->applicant();
        $applications = $applicant->applications()->visibleTo(Auth::user())->with(['jobPosting.manpowerRequest.orgUnit', 'offers.approvals'])->get();

        return view('hrm::livewire.recruitment.applicant-profile', [
            'applicant' => $applicant,
            'applications' => $applications,
            'hiddenApplications' => Application::where('applicant_id', $applicant->id)->count() - $applications->count(),
            'postings' => $this->postingOptions($applicant),
        ]);
    }
}
