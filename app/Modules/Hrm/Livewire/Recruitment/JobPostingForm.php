<?php

namespace App\Modules\Hrm\Livewire\Recruitment;

use App\Modules\Hrm\Enums\JobPostingStatus;
use App\Modules\Hrm\Models\JobPosting;
use App\Modules\Hrm\Models\ManpowerRequest;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * New / Edit / Delete job posting (modal "job-posting-form"). [REC-02]
 *
 *   - New: pick an APPROVED request (of a visible org unit) that still has openings to advertise.
 *     Picking it prefills the title (designation) and the openings.
 *   - The request cannot be changed afterwards: the posting belongs to it.
 *   - Openings may not exceed ManpowerRequest::openingsToAdvertise().
 *   - A Closed posting cannot be edited (reopen it first, 6.3c); only a Draft can be deleted.
 *   - New postings start as Draft; Open / Close are buttons on the posting's page (6.3c).
 *
 * $dispatch('create-job-posting', { requestId: 5 }) or the URL postings?for=5 opens the form with that request chosen.
 */
class JobPostingForm extends Component
{
    /** null = creating a new posting */
    #[Locked]
    public ?int $recordId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    /**
     * Arriving from a request's "Create job posting" button (postings?for=7): open the form straight away,
     * with that request chosen. An unknown / not offered id just opens nothing.
     */
    public function mount(?int $for = null): void
    {
        if ($for !== null && array_key_exists($for, $this->requestOptions())) {
            $this->create($for);
        }
    }

    #[On('create-job-posting')]
    public function create(?int $requestId = null): void
    {
        $this->recordId = null;
        $this->form = [
            'manpower_request_id' => '', 'title' => '', 'description' => '', 'requirements' => '', 'openings' => 1,
            'open_date' => today()->toDateString(), 'close_date' => '',
        ];
        $this->resetValidation();

        if ($requestId !== null && array_key_exists($requestId, $this->requestOptions())) {
            $this->form['manpower_request_id'] = $requestId;
            $this->updatedFormManpowerRequestId();
        }

        Flux::modal('job-posting-form')->show();
    }

    #[On('edit-job-posting')]
    public function edit(int $id): void
    {
        $posting = $this->find($id);

        $this->recordId = $posting->id;
        $this->form = [
            'manpower_request_id' => $posting->manpower_request_id,
            'title' => $posting->title,
            'description' => $posting->description ?? '',
            'requirements' => $posting->requirements ?? '',
            'openings' => $posting->openings,
            'open_date' => $posting->open_date->toDateString(),
            'close_date' => $posting->close_date?->toDateString() ?? '',
        ];
        $this->resetValidation();

        Flux::modal('job-posting-form')->show();
    }

    /** A request was picked (new posting only): prefill the title and offer all its remaining openings. */
    public function updatedFormManpowerRequestId(): void
    {
        $request = $this->chosenRequest();
        if ($request === null) {
            return;
        }

        $this->form['title'] = $request->designation->name ?? '';
        $this->form['openings'] = max(1, $request->openingsToAdvertise());
    }

    public function save(): void
    {
        $posting = $this->recordId ? $this->find($this->recordId) : null;
        if ($posting?->status === JobPostingStatus::Closed) {
            $this->addError('form.title', __('This posting is closed. Reopen it first to change it.'));

            return;
        }

        $request = $posting !== null ? $posting->manpowerRequest : $this->chosenRequest();
        $maxOpenings = $request?->openingsToAdvertise($posting?->id) ?? 0;

        $values = $this->validate([
            // New: one of the offered requests. Edit: the request stays as it was (not validated, not saved).
            'form.manpower_request_id' => $posting ? [] : ['required', Rule::in(array_keys($this->requestOptions()))],
            'form.title' => ['required', 'string', 'max:150'],
            'form.description' => ['nullable', 'string', 'max:10000'],
            'form.requirements' => ['nullable', 'string', 'max:10000'],
            'form.openings' => ['required', 'integer', 'min:1', 'max:'.max(1, $maxOpenings)],
            'form.open_date' => ['required', 'date'],
            'form.close_date' => ['nullable', 'date', 'after_or_equal:form.open_date'],
        ], [
            'form.openings.max' => __('The request has only :max opening(s) left to advertise.', ['max' => $maxOpenings]),
        ], [
            'form.manpower_request_id' => __('manpower request'), 'form.open_date' => __('open date'), 'form.close_date' => __('close date'),
        ])['form'];

        if ($maxOpenings === 0) {
            $this->addError('form.openings', __('All openings of this request are already advertised or filled.'));

            return;
        }

        $values = array_map(fn ($v) => $v === '' ? null : $v, $values);

        if ($posting) {
            unset($values['manpower_request_id']);
            $posting->update($values);
        } else {
            $posting = JobPosting::create([...$values, 'created_by_user_id' => Auth::id()]);
        }

        $this->finish(__('Posting :no saved.', ['no' => $posting->number()]));
    }

    public function delete(): void
    {
        $posting = $this->find((int) $this->recordId);
        if ($posting->status !== JobPostingStatus::Draft) {
            $this->addError('form.title', __('Only a draft can be deleted. Close the posting instead.'));

            return;
        }

        $posting->getConnection()->transaction(fn () => $posting->delete());

        // Back to the list: also when deleting from the posting's own page, which no longer exists.
        session()->flash('toast', ['message' => __('Posting :no deleted.', ['no' => $posting->number()]), 'variant' => 'success']);
        $this->redirectRoute('hrm.recruitment.postings', navigate: true);
    }

    /** Only postings the user may see: someone else's id gives 404. */
    private function find(int $id): JobPosting
    {
        return JobPosting::query()->visibleTo(Auth::user())->with('manpowerRequest')->findOrFail($id);
    }

    /** The request picked in the "New" form, if it is one of the offered ones. */
    private function chosenRequest(): ?ManpowerRequest
    {
        $id = (int) ($this->form['manpower_request_id'] ?? 0);

        return array_key_exists($id, $this->requestOptions())
            ? ManpowerRequest::with('designation')->find($id)
            : null;
    }

    /**
     * Approved requests of visible org units that still have openings to advertise.
     * "MPR-0007 · 2 × Sales Officer (Sales)"
     *
     * @return array<int, string>
     */
    private function requestOptions(): array
    {
        return ManpowerRequest::query()->approved()->visibleTo(Auth::user())
            ->whereColumn('hired_count', '<', 'headcount')   // fully hired ones are skipped in SQL already
            ->with(['designation', 'orgUnit'])->orderByDesc('id')->get()
            ->filter(fn (ManpowerRequest $r) => $r->openingsToAdvertise() > 0)
            ->mapWithKeys(fn (ManpowerRequest $r) => [
                $r->id => $r->number().' · '.$r->openingsToAdvertise().' × '.($r->designation->name ?? '?').' ('.($r->orgUnit->name ?? '?').')',
            ])->all();
    }

    private function finish(string $message): void
    {
        Flux::modal('job-posting-form')->close();
        $this->dispatch('job-posting-saved');
        $this->dispatch('toast', message: $message, variant: 'success');
    }

    public function render(): View
    {
        $posting = $this->recordId ? JobPosting::query()->visibleTo(Auth::user())->with('manpowerRequest.designation', 'manpowerRequest.orgUnit')->find($this->recordId) : null;

        return view('hrm::livewire.recruitment.job-posting-form', [
            'posting' => $posting,
            'editable' => $posting === null || $posting->status !== JobPostingStatus::Closed,
            'requests' => $posting ? [] : $this->requestOptions(),
        ]);
    }
}
