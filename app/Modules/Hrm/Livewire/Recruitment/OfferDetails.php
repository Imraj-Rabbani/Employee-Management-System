<?php

namespace App\Modules\Hrm\Livewire\Recruitment;

use App\Modules\Hrm\Actions\MakeOffer;
use App\Modules\Hrm\Enums\OfferStatus;
use App\Modules\Hrm\Models\JobOffer;
use App\Modules\Hrm\Models\RejectionReason;
use App\Modules\Hrm\Notifications\OfferLetterSent;
use App\Modules\Hrm\Services\OfferLetter;
use App\Modules\Workflow\Exceptions\WorkflowException;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * One job offer's page: status, details + salary comparison, approval timeline, and the buttons for
 * where it is now. [REC-06]
 *
 *   Draft / sent back   Edit · Send for approval
 *   Approved            Download letter · Email letter to applicant · Record answer
 *   Letter sent         Download letter · Email again · Record answer
 *   Accepted            Hire (HireForm, needs permission to add employees) → then a link to the employee       Declined / not approved: nothing more; a new offer can be made
 *
 * Permission: the page route needs hrm.recruitment.manage (re-checked on every click); the offer is found
 * through its application's visibleTo().
 */
class OfferDetails extends Component
{
    #[Locked]
    public int $offerId;

    /** The "Record answer" modal. */
    public string $answer = '';

    public string $answeredOn = '';

    public int|string $reasonId = '';

    public string $note = '';

    public function submit(): void
    {
        $offer = $this->offer();
        if (! $offer->isEditable()) {
            $this->dispatch('toast', message: __('This offer is already waiting for approval or answered.'), variant: 'danger');

            return;
        }

        try {
            $offer->submitForApproval(Auth::user());
        } catch (WorkflowException $e) {
            $this->dispatch('toast', message: $e->getMessage(), variant: 'danger');

            return;
        }

        $this->dispatch('toast', message: __('Offer :no sent for approval.', ['no' => $offer->number()]), variant: 'success');
    }

    /** Emails the letter (PDF attached) to the applicant and marks it sent. */
    public function emailLetter(MakeOffer $offers, OfferLetter $letter): void
    {
        $offer = $this->offer();
        $applicant = $offer->application->applicant;

        if (! in_array($offer->status(), [OfferStatus::Approved, OfferStatus::LetterSent], true)) {
            $this->dispatch('toast', message: __('Only an approved offer that has not been answered can be sent.'), variant: 'danger');

            return;
        }
        if ($applicant->email === null) {
            $this->dispatch('toast', message: __(':name has no email address. Download the letter and send it another way.', ['name' => $applicant->name]), variant: 'danger');

            return;
        }

        Notification::route('mail', [$applicant->email => $applicant->name])->notify(new OfferLetterSent(
            companyName: tenant()->name ?? (string) config('app.name'),
            applicantName: $applicant->first_name,
            designation: (string) $offer->designation?->name,
            answerBy: $offer->expires_on ? format_date($offer->expires_on) : null,
            fileName: $letter->fileName($offer),
            pdfBase64: base64_encode($letter->content($offer)),
        ));
        $offers->letterSent($offer);

        $this->dispatch('toast', message: __('Offer letter emailed to :email.', ['email' => $applicant->email]), variant: 'success');
    }

    public function openAnswer(): void
    {
        $this->answer = '';
        $this->answeredOn = today()->toDateString();
        $this->reasonId = '';
        $this->note = '';
        $this->resetValidation();
        Flux::modal('offer-answer')->show();
    }

    public function saveAnswer(MakeOffer $offers): void
    {
        $this->validate([
            'answer' => ['required', 'in:'.JobOffer::ACCEPTED.','.JobOffer::DECLINED],
            'answeredOn' => ['required', 'date', 'before_or_equal:today'],
            'reasonId' => [$this->answer === JobOffer::DECLINED ? 'required' : 'nullable'],
            'note' => ['nullable', 'string', 'max:2000'],
        ], [], ['answer' => __('answer'), 'answeredOn' => __('date'), 'reasonId' => __('reason')]);

        $offer = $this->offer();
        try {
            if ($this->answer === JobOffer::ACCEPTED) {
                $offers->accept($offer, $this->answeredOn);
            } else {
                $offers->decline($offer, $this->answeredOn, (int) $this->reasonId, trim($this->note) ?: null, Auth::id());
            }
        } catch (\LogicException $e) {
            $this->addError('answer', $e->getMessage());

            return;
        }

        Flux::modal('offer-answer')->close();
        $this->dispatch('toast', message: $this->answer === JobOffer::ACCEPTED
            ? __(':name accepted the offer.', ['name' => $offer->application->applicant->name])
            : __(':name declined the offer. The application is withdrawn.', ['name' => $offer->application->applicant->name]), variant: 'success');
    }

    /** The edit modal saved: draw again. */
    #[On('offer-saved')]
    public function refreshOffer(): void {}

    private function offer(): JobOffer
    {
        return JobOffer::query()
            ->whereHas('application', fn ($q) => $q->visibleTo(Auth::user()))
            ->with(['application.applicant', 'application.hiredEmployee', 'application.jobPosting.manpowerRequest.orgUnit', 'designation', 'grade', 'employmentType', 'approvals'])
            ->findOrFail($this->offerId);
    }

    public function render(): View
    {
        $offer = $this->offer();

        return view('hrm::livewire.recruitment.offer-details', [
            'offer' => $offer,
            'status' => $offer->status(),
            'reasons' => RejectionReason::where('is_active', true)->orderBy('name')->pluck('name', 'id')->all(),
        ]);
    }
}
