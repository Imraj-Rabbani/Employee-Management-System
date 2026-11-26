<?php

namespace App\Modules\Hrm\Livewire\Recruitment;

use App\Modules\Hrm\Actions\ScheduleInterview;
use App\Modules\Hrm\Enums\EmployeeStatus;
use App\Modules\Hrm\Enums\InterviewStatus;
use App\Modules\Hrm\Models\Application;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\Interview;
use App\Modules\Hrm\Scopes\VisibleToCurrentUser;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Schedule an interview round / change a planned one (modal "interview-form").
 *
 *     $dispatch('schedule-interview', { applicationId: 12 })          new round (the row's calendar button)
 *     $dispatch('schedule-interview', { applicationIds: [12, 13] })   several applicants at once (ticked rows)
 *     $dispatch('edit-interview', { id: 4 })                          change a planned round (from the Interviews card)
 *
 * Several applicants: each gets their OWN round with the same panel, place and length, either one after
 * another (back-to-back slots from the start time, the default) or all at the same time (a group interview).
 * Applicants who cannot be scheduled (closed, a round already planned) are skipped and named in the message.
 *
 * The panel is picked with a search box (name or code) → "Add"; any ACTIVE employee can be invited,
 * also from departments outside the user's own access. The rules are in ScheduleInterview.
 * Permission: only on the posting page (hrm.recruitment.manage); the application is found through
 * Application::visibleTo().
 */
class InterviewForm extends Component
{
    /** @var list<int> the applications a NEW round is planned for, in this order */
    #[Locked]
    public array $applicationIds = [];

    /** Several applicants: 'one_after_another' (back-to-back slots) or 'same_time'. */
    public string $timing = 'one_after_another';

    /** null = scheduling a new round */
    #[Locked]
    public ?int $interviewId = null;

    /** @var array{scheduled_at: string, duration_minutes: int|string, location: string, notes: string} */
    public array $form = ['scheduled_at' => '', 'duration_minutes' => 30, 'location' => '', 'notes' => ''];

    /** @var list<int> employee ids on the panel */
    public array $panel = [];

    public string $search = '';

    /** @param  list<int|string>  $applicationIds */
    #[On('schedule-interview')]
    public function schedule(?int $applicationId = null, array $applicationIds = []): void
    {
        $ids = array_map('intval', $applicationIds !== [] ? $applicationIds : [(int) $applicationId]);
        $found = Application::query()->visibleTo(Auth::user())->whereKey($ids)->pluck('id')->all();

        $this->applicationIds = array_values(array_filter($ids, fn (int $id) => in_array($id, $found, true)));   // the pipeline's order
        abort_if($this->applicationIds === [], 404);
        $this->interviewId = null;
        $this->timing = 'one_after_another';
        $this->form = ['scheduled_at' => now()->addDay()->setTime(10, 0)->format('Y-m-d\TH:i'), 'duration_minutes' => 30, 'location' => '', 'notes' => ''];
        $this->panel = [];
        $this->search = '';
        $this->resetValidation();

        Flux::modal('interview-form')->show();
    }

    #[On('edit-interview')]
    public function edit(int $id): void
    {
        $interview = $this->interview($id);

        $this->applicationIds = [$interview->application_id];
        $this->interviewId = $interview->id;
        $this->form = [
            'scheduled_at' => $interview->scheduled_at->format('Y-m-d\TH:i'),
            'duration_minutes' => $interview->duration_minutes,
            'location' => $interview->location ?? '',
            'notes' => $interview->notes ?? '',
        ];
        $this->panel = $interview->panelists()->pluck('employee_id')->map(fn ($id) => (int) $id)->all();
        $this->search = '';
        $this->resetValidation();

        Flux::modal('interview-form')->show();
    }

    public function addPanelist(int $employeeId): void
    {
        if (! in_array($employeeId, $this->panel, true)) {
            $this->panel[] = $employeeId;
        }
        $this->search = '';
    }

    public function removePanelist(int $employeeId): void
    {
        $this->panel = array_values(array_filter($this->panel, fn (int $id) => $id !== $employeeId));
    }

    public function save(ScheduleInterview $action): void
    {
        $details = $this->validate([
            'form.scheduled_at' => ['required', 'date'],
            'form.duration_minutes' => ['required', 'integer', 'min:5', 'max:600'],
            'form.location' => ['nullable', 'string', 'max:300'],
            'form.notes' => ['nullable', 'string', 'max:2000'],
        ], [], ['form.scheduled_at' => __('date and time'), 'form.duration_minutes' => __('duration')])['form'];

        if ($this->interviewId) {
            $interview = $this->interview($this->interviewId);
            if ($problem = $action->problemForUpdate($interview, $this->panel)) {
                $this->addError('panel', $problem);

                return;
            }
            $action->update($interview, $details, $this->panel);
            $message = __(':round changed. The panel has been told.', ['round' => $interview->title()]);
            $skipped = false;
        } else {
            $this->validate(['timing' => ['required', 'in:one_after_another,same_time']]);

            // Check every applicant first: when NONE can be scheduled (e.g. no panel), nothing is saved.
            $problems = [];
            $ready = [];
            foreach ($this->applications() as $application) {
                if ($problem = $action->problem($application, $this->panel)) {
                    $problems[] = (count($this->applicationIds) > 1 ? $application->applicant->name.': ' : '').$problem;
                } else {
                    $ready[] = $application;
                }
            }
            if ($ready === []) {
                $this->addError('panel', implode(' ', array_unique($problems)));

                return;
            }

            foreach ($ready as $i => $application) {
                $action->handle($application, [...$details, 'scheduled_at' => $this->startOf($i)], $this->panel, Auth::id());
            }
            $message = implode(' ', [
                count($ready) === 1 && count($this->applicationIds) === 1
                    ? __('Interview scheduled. The panel has been told.')
                    : trans_choice(':count interview scheduled. The panel has been told.|:count interviews scheduled. The panel has been told.', count($ready)),
                ...$problems,
            ]);
            $skipped = $problems !== [];
        }

        Flux::modal('interview-form')->close();
        $this->dispatch('interview-saved');   // the pipeline and the Interviews card redraw
        $this->dispatch('toast', message: $message, variant: $skipped ? 'warning' : 'success');
    }

    /** Start of the $i-th applicant's interview: back-to-back slots, or all at the start time. */
    private function startOf(int $i): string
    {
        $start = Carbon::parse($this->form['scheduled_at']);

        return ($this->timing === 'same_time' ? $start : $start->addMinutes($i * (int) $this->form['duration_minutes']))->toDateTimeString();
    }

    /** @return Collection<int, Application> the chosen applications, in the chosen order */
    private function applications(): Collection
    {
        $found = Application::query()->visibleTo(Auth::user())->with('applicant')->whereKey($this->applicationIds)->get()->keyBy('id');

        return collect($this->applicationIds)->map(fn (int $id) => $found->get($id))->filter()->values();
    }

    private function interview(int $id): Interview
    {
        return Interview::query()->whereHas('application', fn ($q) => $q->visibleTo(Auth::user()))
            ->where('status', InterviewStatus::Scheduled)->findOrFail($id);
    }

    public function render(): View
    {
        $employees = fn () => Employee::withoutGlobalScope(VisibleToCurrentUser::class)->with('designation');

        $results = trim($this->search) === '' ? collect() : $employees()
            ->where('status', EmployeeStatus::Active)
            ->whereNotIn('id', $this->panel)
            ->where(fn ($q) => $q->where('first_name', 'like', '%'.trim($this->search).'%')
                ->orWhere('last_name', 'like', '%'.trim($this->search).'%')
                ->orWhere('code', 'like', trim($this->search).'%'))
            ->orderBy('first_name')->limit(8)->get();

        return view('hrm::livewire.recruitment.interview-form', [
            // New: each applicant with the start of their slot (redrawn as the time / length / timing change).
            'schedule' => $this->interviewId ? collect() : $this->applications()->map(fn (Application $a, int $i) => [
                'name' => $a->applicant->name,
                'at' => $this->form['scheduled_at'] !== '' && is_numeric($this->form['duration_minutes']) ? rescue(fn () => format_datetime(Carbon::parse($this->startOf($i))), '', false) : '',
            ]),
            'interview' => $this->interviewId ? Interview::with('panelists')->find($this->interviewId) : null,
            'chosen' => $this->panel === [] ? collect() : $employees()->whereKey($this->panel)->get(),
            'results' => $results,
        ]);
    }
}
