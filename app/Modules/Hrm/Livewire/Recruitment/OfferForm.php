<?php

namespace App\Modules\Hrm\Livewire\Recruitment;

use App\Modules\Hrm\Actions\MakeOffer;
use App\Modules\Hrm\Models\Application;
use App\Modules\Hrm\Models\Designation;
use App\Modules\Hrm\Models\EmploymentType;
use App\Modules\Hrm\Models\Grade;
use App\Modules\Hrm\Models\JobOffer;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Make / change a job offer (modal "offer-form"). [REC-06]
 *
 *     $dispatch('create-offer', { applicationId: 12 })   new (from the pipeline); prefilled from the manpower request
 *     $dispatch('edit-offer', { id: 3 })                 a draft or an offer sent back for changes
 *
 * The salary comparison (grade band / request range / expected salary) redraws while typing: it is
 * JobOffer::comparisons() on an UNSAVED offer built from the form. The rules are in MakeOffer.
 * Permission: the posting / offer pages need hrm.recruitment.manage; the application through visibleTo().
 */
class OfferForm extends Component
{
    #[Locked]
    public ?int $applicationId = null;

    #[Locked]
    public ?int $offerId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    #[On('create-offer')]
    public function create(int $applicationId): void
    {
        $application = $this->application($applicationId);
        $request = $application->jobPosting->manpowerRequest;

        $this->applicationId = $application->id;
        $this->offerId = null;
        $this->form = [
            'gross_salary' => $application->applicant->expected_salary ?? $request->salary_max ?? '',
            'grade_id' => $request->grade_id ?? '',
            'designation_id' => $request->designation_id,
            'employment_type_id' => $request->employment_type_id ?? '',
            'joining_date' => ($request->needed_by && $request->needed_by->isFuture() ? $request->needed_by : today()->addMonth())->toDateString(),
            'expires_on' => today()->addWeek()->toDateString(),
            'terms' => '',
        ];
        $this->resetValidation();

        Flux::modal('offer-form')->show();
    }

    #[On('edit-offer')]
    public function edit(int $id): void
    {
        $offer = $this->offer($id);

        $this->applicationId = $offer->application_id;
        $this->offerId = $offer->id;
        $this->form = [
            'gross_salary' => $offer->gross_salary,
            'grade_id' => $offer->grade_id ?? '',
            'designation_id' => $offer->designation_id,
            'employment_type_id' => $offer->employment_type_id ?? '',
            'joining_date' => $offer->joining_date->toDateString(),
            'expires_on' => $offer->expires_on?->toDateString() ?? '',
            'terms' => $offer->terms ?? '',
        ];
        $this->resetValidation();

        Flux::modal('offer-form')->show();
    }

    public function save(MakeOffer $offers): void
    {
        $values = $this->validate([
            'form.gross_salary' => ['required', 'numeric', 'min:1', 'max:999999999'],
            'form.grade_id' => ['nullable', Rule::exists(Grade::class, 'id')],
            'form.designation_id' => ['required', Rule::exists(Designation::class, 'id')],
            'form.employment_type_id' => ['nullable', Rule::exists(EmploymentType::class, 'id')],
            'form.joining_date' => ['required', 'date', 'after_or_equal:today'],
            'form.expires_on' => ['nullable', 'date', 'after_or_equal:today', 'before_or_equal:form.joining_date'],
            'form.terms' => ['nullable', 'string', 'max:5000'],
        ], [], [
            'form.gross_salary' => __('gross salary'), 'form.designation_id' => __('designation'),
            'form.joining_date' => __('joining date'), 'form.expires_on' => __('answer-by date'),
        ])['form'];

        if ($this->offerId) {
            $offer = $this->offer($this->offerId);
            if (! $offer->isEditable()) {
                $this->addError('form.gross_salary', __('This offer can no longer be changed (:status).', ['status' => $offer->status()->label()]));

                return;
            }
            $offers->update($offer, $values);
            Flux::modal('offer-form')->close();
            $this->dispatch('offer-saved');
            $this->dispatch('toast', message: __('Offer :no saved.', ['no' => $offer->number()]), variant: 'success');

            return;
        }

        $application = $this->application((int) $this->applicationId);
        if ($problem = $offers->problem($application)) {
            $this->addError('form.gross_salary', $problem);

            return;
        }

        $offer = $offers->create($application, $values, Auth::id());

        session()->flash('toast', ['message' => __('Offer :no saved as a draft. Check it, then send it for approval.', ['no' => $offer->number()]), 'variant' => 'success']);
        $this->redirectRoute('hrm.recruitment.offers.show', ['id' => $offer->id], navigate: true);
    }

    private function application(int $id): Application
    {
        return Application::query()->visibleTo(Auth::user())
            ->with(['applicant', 'jobPosting.manpowerRequest'])->findOrFail($id);
    }

    private function offer(int $id): JobOffer
    {
        return JobOffer::query()->whereHas('application', fn ($q) => $q->visibleTo(Auth::user()))->findOrFail($id);
    }

    public function render(): View
    {
        $application = $this->applicationId ? $this->application($this->applicationId) : null;

        // The live comparison: an unsaved offer from what is typed now.
        $preview = null;
        if ($application && is_numeric($this->form['gross_salary'] ?? null)) {
            $preview = new JobOffer(['gross_salary' => $this->form['gross_salary']]);
            $preview->setRelation('application', $application);
            $preview->setRelation('grade', ($this->form['grade_id'] ?? '') !== '' ? Grade::find($this->form['grade_id']) : null);
        }

        return view('hrm::livewire.recruitment.offer-form', [
            'application' => $application,
            'comparisons' => $preview?->comparisons() ?? [],
            'grades' => Grade::ordered()->get()->mapWithKeys(fn (Grade $g) => [$g->id => $g->name.($g->salaryRange() ? ' ('.$g->salaryRange().')' : '')])->all(),
            'designations' => Designation::where('is_active', true)->orderBy('name')->pluck('name', 'id')->all(),
            'types' => EmploymentType::orderBy('name')->pluck('name', 'id')->all(),
        ]);
    }
}
