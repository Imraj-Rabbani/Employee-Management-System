<?php

namespace App\Modules\Hrm\Livewire\Recruitment;

use App\Core\Support\TenantUploads;
use App\Modules\Hrm\Actions\ApplyToPosting;
use App\Modules\Hrm\Enums\Gender;
use App\Modules\Hrm\Models\Applicant;
use App\Modules\Hrm\Models\JobPosting;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * New / Edit / Delete applicant (modal "applicant-form"). [REC-03]
 *
 *   New:  personal details + CV + (optional) the open posting they apply to.
 *         $dispatch('create-applicant', { postingId: 3 }) presets the posting (from the posting's page).
 *   Duplicate check: while typing the email / phone, people already entered with them are listed.
 *         Saving a duplicate is refused; "Use this person" applies the EXISTING applicant instead.
 *   Edit: the same fields (no posting: use "Apply to posting" on the profile); after saving, the profile redraws.
 *   Delete: only when they have no applications (the hiring history stays).
 *
 * Permission: every route that shows this form needs hrm.recruitment.manage (re-checked on each click).
 */
class ApplicantForm extends Component
{
    use WithFileUploads;

    #[Locked]
    public ?int $recordId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    /** The posting a NEW applicant applies to ('' = none). */
    public int|string $postingId = '';

    public ?TemporaryUploadedFile $cv = null;

    #[On('create-applicant')]
    public function create(?int $postingId = null): void
    {
        $this->recordId = null;
        $this->form = [
            'first_name' => '', 'last_name' => '', 'email' => '', 'phone' => '', 'gender' => '', 'birth_date' => '',
            'national_id' => '', 'address' => '', 'current_employer' => '', 'current_designation' => '',
            'expected_salary' => '', 'notes' => '',
        ];
        $this->postingId = $postingId !== null && array_key_exists($postingId, $this->postingOptions()) ? $postingId : '';
        $this->reset('cv');
        $this->resetValidation();

        Flux::modal('applicant-form')->show();
    }

    #[On('edit-applicant')]
    public function edit(int $id): void
    {
        $applicant = Applicant::findOrFail($id);

        $this->recordId = $applicant->id;
        $this->form = [
            'first_name' => $applicant->first_name,
            'last_name' => $applicant->last_name ?? '',
            'email' => $applicant->email ?? '',
            'phone' => $applicant->phone ?? '',
            'gender' => $applicant->gender->value ?? '',
            'birth_date' => $applicant->birth_date?->toDateString() ?? '',
            'national_id' => $applicant->national_id ?? '',
            'address' => $applicant->address ?? '',
            'current_employer' => $applicant->current_employer ?? '',
            'current_designation' => $applicant->current_designation ?? '',
            'expected_salary' => $applicant->expected_salary ?? '',
            'notes' => $applicant->notes ?? '',
        ];
        $this->postingId = '';
        $this->reset('cv');
        $this->resetValidation();

        Flux::modal('applicant-form')->show();
    }

    public function save(ApplyToPosting $apply, TenantUploads $uploads): void
    {
        $values = $this->validate([
            'form.first_name' => ['required', 'string', 'max:100'],
            'form.last_name' => ['nullable', 'string', 'max:100'],
            'form.email' => ['nullable', 'required_without:form.phone', 'email', 'max:150'],
            'form.phone' => ['nullable', 'required_without:form.email', 'string', 'max:30', 'regex:/^[0-9+\-\s()]{6,}$/'],
            'form.gender' => ['nullable', Rule::enum(Gender::class)],
            'form.birth_date' => ['nullable', 'date', 'before:today'],
            'form.national_id' => ['nullable', 'string', 'max:30'],
            'form.address' => ['nullable', 'string', 'max:500'],
            'form.current_employer' => ['nullable', 'string', 'max:200'],
            'form.current_designation' => ['nullable', 'string', 'max:100'],
            'form.expected_salary' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'form.notes' => ['nullable', 'string', 'max:5000'],
            'postingId' => $this->recordId ? [] : ['nullable', Rule::in(array_keys($this->postingOptions()))],
            'cv' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:5120'],
        ], [
            'form.email.required_without' => __('Give an email or a phone number (or both).'),
            'form.phone.required_without' => __('Give a phone number or an email (or both).'),
        ], [
            'form.first_name' => __('first name'), 'form.birth_date' => __('date of birth'),
            'form.expected_salary' => __('expected salary'), 'postingId' => __('job posting'), 'cv' => __('CV'),
        ]);
        $values = array_map(fn ($v) => $v === '' ? null : $v, $values['form']);

        // The duplicate check: the unique indexes would refuse it too, but with an unfriendly error.
        if (Applicant::duplicatesOf($values['email'], $values['phone'], $this->recordId)->isNotEmpty()) {
            $this->addError('form.email', __('This person is already entered (same email or phone). Use the existing record below.'));

            return;
        }

        $applicant = $this->recordId ? Applicant::findOrFail($this->recordId) : new Applicant(['created_by_user_id' => Auth::id()]);
        $posting = $this->recordId || $this->postingId === '' ? null : JobPosting::findOrFail((int) $this->postingId);
        if ($posting && ($problem = $apply->problem($applicant, $posting))) {
            $this->addError('postingId', $problem);

            return;
        }

        $oldCv = $applicant->cv_path;
        $applicant->fill($values);
        if ($this->cv) {
            $applicant->cv_path = $uploads->store($this->cv, 'applicants/cv');
            $applicant->cv_name = $this->cv->getClientOriginalName();
        }

        // One transaction: the applicant and their first application are saved together, or neither.
        DB::connection('tenant')->transaction(function () use ($applicant, $posting, $apply) {
            $applicant->save();
            if ($posting) {
                $apply->handle($applicant, $posting, Auth::id());
            }
        });

        if ($this->cv) {
            $uploads->delete($oldCv);   // replaced: the old file goes
        }

        if ($this->recordId) {
            Flux::modal('applicant-form')->close();
            $this->reset('cv');
            $this->dispatch('applicant-saved');
            $this->dispatch('toast', message: __(':name saved.', ['name' => $applicant->name]), variant: 'success');

            return;
        }

        $this->goTo($applicant, __(':name added.', ['name' => $applicant->name]));
    }

    /**
     * The person typed in is already entered: apply THEM (to the chosen posting, if any) and open their profile.
     */
    public function useExisting(int $applicantId, ApplyToPosting $apply): void
    {
        $applicant = Applicant::duplicatesOf($this->form['email'] ?? null, $this->form['phone'] ?? null, $this->recordId)
            ->firstWhere('id', $applicantId) ?? abort(404);

        if ($this->postingId !== '') {
            $posting = JobPosting::findOrFail((int) $this->postingId);
            if ($problem = $apply->problem($applicant, $posting)) {
                $this->addError('postingId', $problem);

                return;
            }
            $apply->handle($applicant, $posting, Auth::id());
            $this->goTo($applicant, __(':name applied to :no.', ['name' => $applicant->name, 'no' => $posting->number()]));

            return;
        }

        $this->goTo($applicant, null);
    }

    public function delete(TenantUploads $uploads): void
    {
        $applicant = Applicant::findOrFail((int) $this->recordId);
        if ($applicant->applications()->exists()) {
            $this->addError('form.first_name', __('This person has applications, so the record stays (hiring history).'));

            return;
        }

        $cv = $applicant->cv_path;
        $applicant->getConnection()->transaction(fn () => $applicant->delete());   // educations / work history go with it
        $uploads->delete($cv);

        session()->flash('toast', ['message' => __(':name deleted.', ['name' => $applicant->name]), 'variant' => 'success']);
        $this->redirectRoute('hrm.recruitment.applicants', navigate: true);
    }

    public function removeCv(TenantUploads $uploads): void
    {
        $applicant = Applicant::findOrFail((int) $this->recordId);
        $uploads->delete($applicant->cv_path);
        $applicant->forceFill(['cv_path' => null, 'cv_name' => null])->save();
    }

    private function goTo(Applicant $applicant, ?string $toast): void
    {
        if ($toast !== null) {
            session()->flash('toast', ['message' => $toast, 'variant' => 'success']);
        }
        $this->redirectRoute('hrm.recruitment.applicants.show', ['applicant' => $applicant->id], navigate: true);
    }

    /**
     * Postings the user can see that take applications today. "JOB-0003 · Sales Officer (Sales)"
     *
     * @return array<int, string>
     */
    private function postingOptions(): array
    {
        return JobPosting::query()->visibleTo(Auth::user())->where('status', 'open')
            ->with('manpowerRequest.orgUnit')->orderByDesc('id')->get()
            ->filter(fn (JobPosting $p) => $p->isAcceptingApplications())
            ->mapWithKeys(fn (JobPosting $p) => [$p->id => $p->number().' · '.$p->title.' ('.($p->manpowerRequest->orgUnit->name ?? '?').')'])
            ->all();
    }

    public function render(): View
    {
        $applicant = $this->recordId ? Applicant::find($this->recordId) : null;

        return view('hrm::livewire.recruitment.applicant-form', [
            'applicant' => $applicant,
            'genders' => Gender::options(),
            'postings' => $this->recordId ? [] : $this->postingOptions(),
            // Live duplicate check: wire:model.blur on email / phone redraws this list.
            'duplicates' => Applicant::duplicatesOf($this->form['email'] ?? null, $this->form['phone'] ?? null, $this->recordId),
        ]);
    }
}
