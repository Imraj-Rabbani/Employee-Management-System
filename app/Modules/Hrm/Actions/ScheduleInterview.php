<?php

namespace App\Modules\Hrm\Actions;

use App\Modules\Hrm\Enums\ApplicationStage;
use App\Modules\Hrm\Enums\EmployeeStatus;
use App\Modules\Hrm\Enums\InterviewStatus;
use App\Modules\Hrm\Models\Application;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\Interview;
use App\Modules\Hrm\Notifications\InterviewScheduled;
use App\Modules\Hrm\Scopes\VisibleToCurrentUser;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use LogicException;

/**
 * Plans an interview round, changes a planned one, and tells the panel. [REC-05]
 * The ONLY place that creates interviews or changes their panel.
 *
 *     $action = app(ScheduleInterview::class);
 *     $problem = $action->problem($application, $employeeIds);           // or problemForUpdate($interview, ...)
 *     $interview = $action->handle($application, ['scheduled_at' => ..., 'location' => ...], $employeeIds, auth()->id());
 *     $action->update($interview, [...], $employeeIds);
 *
 * handle(), in one transaction: next round number (1, 2, 3 ... cancelled rounds keep theirs), the panel,
 * and the application moves to "Interview" if it was still Applied / Shortlisted (with a history row).
 * After the commit every panelist is told: in the bell + email with a login, by email to the office email
 * without one, not at all when they have neither.
 *
 * Rules: the application must still be open, only ONE planned round at a time per application, and the
 * panel = 1 or more ACTIVE employees. Panelists are found WITHOUT the visibility scope: HR may invite an
 * interviewer from any department.
 */
class ScheduleInterview
{
    public function __construct(private MoveApplication $move) {}

    /** @param  list<int>  $employeeIds */
    public function problem(Application $application, array $employeeIds): ?string
    {
        if ($application->stage->isFinal()) {
            return __('This application is closed (:stage).', ['stage' => $application->stage->label()]);
        }

        if ($application->interviews()->where('status', InterviewStatus::Scheduled)->exists()) {
            return __('A round is already planned. Complete or cancel it first.');
        }

        return $this->panelProblem($employeeIds);
    }

    /** @param  list<int>  $employeeIds */
    public function problemForUpdate(Interview $interview, array $employeeIds): ?string
    {
        if ($interview->status !== InterviewStatus::Scheduled) {
            return __('Only a planned interview can be changed.');
        }

        // Someone who already sent their score sheet stays on the panel (their scores count).
        $submitted = $interview->panelists()->whereNotNull('submitted_at')->pluck('employee_id')->all();
        if (array_diff($submitted, $employeeIds) !== []) {
            return __('A panelist who already sent their scores cannot be removed.');
        }

        return $this->panelProblem($employeeIds);
    }

    /**
     * @param  array{scheduled_at: string|Carbon, duration_minutes?: int|null, location?: string|null, notes?: string|null}  $details
     * @param  list<int>  $employeeIds
     */
    public function handle(Application $application, array $details, array $employeeIds, ?int $byUserId = null): Interview
    {
        if ($problem = $this->problem($application, $employeeIds)) {
            throw new LogicException($problem);
        }

        $interview = DB::connection('tenant')->transaction(function () use ($application, $details, $employeeIds, $byUserId) {
            $interview = $application->interviews()->create([
                ...$this->clean($details),
                'round' => (int) $application->interviews()->max('round') + 1,
                'status' => InterviewStatus::Scheduled,
                'created_by_user_id' => $byUserId,
            ]);
            foreach (array_unique($employeeIds) as $employeeId) {
                $interview->panelists()->create(['employee_id' => $employeeId]);
            }

            if ((int) $application->stage->step() < (int) ApplicationStage::Interview->step()) {
                $this->move->handle($application, ApplicationStage::Interview, note: $interview->title().' '.__('scheduled'), byUserId: $byUserId);
            }

            return $interview;
        });

        $this->notify($interview, $interview->panelists()->pluck('employee_id')->all(), changed: false);

        return $interview;
    }

    /**
     * @param  array{scheduled_at: string|Carbon, duration_minutes?: int|null, location?: string|null, notes?: string|null}  $details
     * @param  list<int>  $employeeIds
     */
    public function update(Interview $interview, array $details, array $employeeIds): Interview
    {
        if ($problem = $this->problemForUpdate($interview, $employeeIds)) {
            throw new LogicException($problem);
        }

        $before = [$interview->scheduled_at->toDateTimeString(), $interview->location];
        $oldPanel = $interview->panelists()->pluck('employee_id')->all();

        DB::connection('tenant')->transaction(function () use ($interview, $details, $employeeIds, $oldPanel) {
            $interview->update($this->clean($details));
            $interview->panelists()->whereNotIn('employee_id', $employeeIds)->delete();   // only unsent ones (checked above)
            foreach (array_diff(array_unique($employeeIds), $oldPanel) as $employeeId) {
                $interview->panelists()->create(['employee_id' => $employeeId]);
            }
        });

        // New panelists get the invitation; the others only hear about it when the time or place changed.
        $added = array_values(array_diff($employeeIds, $oldPanel));
        $this->notify($interview, $added, changed: false);
        if ($before !== [$interview->scheduled_at->toDateTimeString(), $interview->location]) {
            $this->notify($interview, array_values(array_intersect($oldPanel, $employeeIds)), changed: true);
        }

        return $interview;
    }

    /** @param  list<int>  $employeeIds */
    private function panelProblem(array $employeeIds): ?string
    {
        if ($employeeIds === []) {
            return __('Add at least one interviewer.');
        }

        $active = Employee::withoutGlobalScope(VisibleToCurrentUser::class)
            ->whereKey($employeeIds)->where('status', EmployeeStatus::Active)->count();

        return $active === count(array_unique($employeeIds)) ? null : __('Interviewers must be active employees.');
    }

    /**
     * @param  array<string, mixed>  $details
     * @return array<string, mixed>
     */
    private function clean(array $details): array
    {
        $details = array_intersect_key($details, array_flip(['scheduled_at', 'duration_minutes', 'location', 'notes']));

        return array_map(fn ($v) => $v === '' ? null : $v, $details) + ['duration_minutes' => 30];
    }

    /** @param  list<int>  $employeeIds */
    private function notify(Interview $interview, array $employeeIds, bool $changed): void
    {
        if ($employeeIds === []) {
            return;
        }

        $interview->loadMissing('application.applicant', 'application.jobPosting');
        $posting = $interview->application->jobPosting;

        $employees = Employee::withoutGlobalScope(VisibleToCurrentUser::class)->with('user')->whereKey($employeeIds)->get();
        foreach ($employees as $employee) {
            $notification = new InterviewScheduled(
                applicantName: $interview->application->applicant->name,
                postingTitle: $posting->number().' · '.$posting->title,
                round: $interview->round,
                when: format_datetime($interview->scheduled_at),
                location: $interview->location,
                notes: $interview->notes,
                url: $employee->user ? route('ess.interviews') : null,
                changed: $changed,
            );

            if ($employee->user) {
                $employee->user->notify($notification);
            } elseif ($employee->office_email) {
                Notification::route('mail', $employee->office_email)->notify($notification);
            }
        }
    }
}
