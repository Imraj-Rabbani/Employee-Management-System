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
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Schedule an interview round / change a planned one (modal "interview-form"). [REC-05]
 *
 *     $dispatch('schedule-interview', { applicationId: 12 })   new round (from the pipeline)
 *     $dispatch('edit-interview', { id: 4 })                   change a planned round (from the Interviews card)
 *
 * The panel is picked with a search box (name or code) → "Add"; any ACTIVE employee can be invited,
 * also from departments outside the user's own access. The rules are in ScheduleInterview.
 * Permission: only on the posting page (hrm.recruitment.manage); the application is found through
 * Application::visibleTo().
 */
class InterviewForm extends Component
{
    #[Locked]
    public ?int $applicationId = null;

    /** null = scheduling a new round */
    #[Locked]
    public ?int $interviewId = null;

    /** @var array{scheduled_at: string, duration_minutes: int|string, location: string, notes: string} */
    public array $form = ['scheduled_at' => '', 'duration_minutes' => 30, 'location' => '', 'notes' => ''];

    /** @var list<int> employee ids on the panel */
    public array $panel = [];

    public string $search = '';

    #[On('schedule-interview')]
    public function schedule(int $applicationId): void
    {
        $application = $this->application($applicationId);

        $this->applicationId = $application->id;
        $this->interviewId = null;
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

        $this->applicationId = $interview->application_id;
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
        } else {
            $application = $this->application((int) $this->applicationId);
            if ($problem = $action->problem($application, $this->panel)) {
                $this->addError('panel', $problem);

                return;
            }
            $interview = $action->handle($application, $details, $this->panel, Auth::id());
            $message = __(':round scheduled. The panel has been told.', ['round' => $interview->title()]);
        }

        Flux::modal('interview-form')->close();
        $this->dispatch('interview-saved');   // the pipeline and the Interviews card redraw
        $this->dispatch('toast', message: $message, variant: 'success');
    }

    private function application(int $id): Application
    {
        return Application::query()->visibleTo(Auth::user())->with('applicant')->findOrFail($id);
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
            'application' => $this->applicationId ? Application::with('applicant')->find($this->applicationId) : null,
            'interview' => $this->interviewId ? Interview::with('panelists')->find($this->interviewId) : null,
            'chosen' => $this->panel === [] ? collect() : $employees()->whereKey($this->panel)->get(),
            'results' => $results,
        ]);
    }
}
