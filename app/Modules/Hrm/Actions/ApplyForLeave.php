<?php

namespace App\Modules\Hrm\Actions;

use App\Core\Support\TenantUploads;
use App\Modules\Hrm\Data\LeaveCheck;
use App\Modules\Hrm\Data\LeaveProblem;
use App\Modules\Hrm\Data\LeaveYear;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\LeaveApplication;
use App\Modules\Hrm\Models\LeaveApplicationDay;
use App\Modules\Hrm\Models\LeaveType;
use App\Modules\Hrm\Services\CarryForwardLeave;
use App\Modules\Hrm\Services\LeaveRequestInputs;
use App\Modules\Hrm\Services\LeaveRules;
use App\Modules\Payroll\Models\AttendanceDay;
use App\Modules\Workflow\Exceptions\WorkflowException;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

/**
 * Applies for leave (new, or changed after it was sent back) and sends it for approval. [LV-04]
 * The ONE way leave applications are written: by HR (HRM → Leave → Applications) and by the employee
 * (Self Service → My Leave Requests).
 *
 *     $check = $action->evaluate($employee, $type, $values);           // also the live preview in the form
 *     if ($check->ok()) { $action->handle($employee, $type, $values, $check, attachment: $file); }
 *
 * evaluate() = LeaveRules (8.3) + what only the database knows:
 *   - times taken / overlap with my other active leave (from leave_applications)
 *   - all counted days in ONE leave year (apply separately for each year: each has its own balance)
 *   - no counted day in a closed payroll (attendance_days.is_locked), nor in a closed leave year (8.5)
 *   - a changed application must have been sent back
 *
 * $values: from_date, to_date ('Y-m-d'), is_half_day, reason, delegatee_id, contact_during_leave.
 */
class ApplyForLeave
{
    public function __construct(
        private LeaveRequestInputs $inputs,
        private LeaveRules $rules,
        private TenantUploads $uploads,
    ) {}

    /** @param  array<string, mixed>  $values */
    public function evaluate(Employee $employee, LeaveType $type, array $values, ?LeaveApplication $existing = null, bool $byHr = false, bool $newAttachment = false): LeaveCheck
    {
        $timesTaken = LeaveApplication::query()->active()
            ->where('employee_id', $employee->id)->where('leave_type_id', $type->id)
            ->when($existing, fn ($q) => $q->whereKeyNot($existing->id))
            ->count();

        $request = $this->inputs->build(
            $employee, $type, $values['from_date'], $values['to_date'],
            timesTaken: $timesTaken,
            overlaps: false,
            halfDay: (bool) ($values['is_half_day'] ?? false),
            hasReason: trim((string) ($values['reason'] ?? '')) !== '',
            hasAttachment: $newAttachment || $existing?->attachment_path !== null,
            byHr: $byHr,
        );
        $check = $this->rules->check($type, $request);

        // The overlap needs the COUNTED dates (sandwich days included), so it is known only now.
        if ($check->dates !== [] && $this->overlaps($employee, array_keys($check->dates), $existing)) {
            $check = $this->rules->check($type, $request->withOverlaps(true));
        }

        $more = [];
        if ($existing !== null && ! $existing->isEditable()) {
            $more[] = new LeaveProblem('not_editable', 'from_date', __('This application is waiting for approval or already decided, so it cannot be changed.'));
        }
        if ($check->dates !== []) {
            $years = array_unique(array_map(fn (string $d) => LeaveYear::containing($d)->year, array_keys($check->dates)));
            if (count($years) > 1) {
                $more[] = new LeaveProblem('two_years', 'to_date', __('The leave runs into the next leave year (from :date). Apply separately for each year.', [
                    'date' => format_date(LeaveYear::of(min($years))->next()->start()),
                ]));
            }
            $year = LeaveYear::containing(array_key_first($check->dates));
            if (CarryForwardLeave::isClosed($year, CarbonImmutable::now(settings('timezone', config('app.timezone'))))) {
                $more[] = new LeaveProblem('year_closed', 'from_date', __('The leave year :year is closed (carried forward): no more leave can be entered in it.', ['year' => $year->label()]));
            }
            if (AttendanceDay::query()->where('employee_id', $employee->id)->whereIn('date', array_keys($check->dates))->where('is_locked', true)->exists()) {
                $more[] = new LeaveProblem('locked', 'from_date', __('The payroll for some of these days is closed: no more leave can be entered for them.'));
            }
        }

        return $more === [] ? $check : $check->withProblems(...$more);
    }

    /**
     * Saves the application with its counted days and sends it for approval (again, when it was sent back),
     * all in ONE transaction: when nobody can approve it (WorkflowException, message for the user) nothing is kept.
     *
     * @param  array<string, mixed>  $values
     *
     * @throws WorkflowException
     */
    public function handle(Employee $employee, LeaveType $type, array $values, LeaveCheck $check, ?LeaveApplication $existing = null, ?UploadedFile $attachment = null): LeaveApplication
    {
        if (! $check->ok()) {
            throw new InvalidArgumentException('ApplyForLeave::handle() needs a check without problems: call evaluate() first.');
        }

        $newPath = $attachment ? $this->uploads->store($attachment, 'leave') : null;
        $newName = $attachment?->getClientOriginalName();
        $oldPath = $existing?->attachment_path;

        try {
            $application = (new LeaveApplication)->getConnection()->transaction(function () use ($employee, $type, $values, $check, $existing, $newPath, $newName) {
                $attributes = [
                    'employee_id' => $employee->id,
                    'leave_type_id' => $type->id,
                    'from_date' => $values['from_date'],
                    'to_date' => $values['to_date'],
                    'is_half_day' => (bool) ($values['is_half_day'] ?? false),
                    'days' => $check->days,
                    'leave_year' => LeaveYear::containing(array_key_first($check->dates))->year,
                    'delegatee_id' => ($values['delegatee_id'] ?? '') ?: null,
                    'contact_during_leave' => trim((string) ($values['contact_during_leave'] ?? '')) ?: null,
                    'reason' => trim((string) ($values['reason'] ?? '')) ?: null,
                    ...($newPath ? ['attachment_path' => $newPath, 'attachment_name' => $newName] : []),
                ];

                $application = $existing ?? new LeaveApplication(['requested_by_user_id' => Auth::id()]);
                $application->fill($attributes)->save();

                $application->leaveDays()->delete();
                foreach ($check->dates as $date => $days) {
                    LeaveApplicationDay::create(['leave_application_id' => $application->id, 'employee_id' => $employee->id, 'date' => $date, 'days' => $days]);
                }

                $application->submitForApproval();

                return $application;
            });
        } catch (\Throwable $e) {
            $this->uploads->delete($newPath);   // nothing was saved: do not keep the file either

            throw $e;
        }

        if ($newPath !== null && $oldPath !== null) {
            $this->uploads->delete($oldPath);   // replaced
        }

        return $application;
    }

    /** @param  list<string>  $dates */
    private function overlaps(Employee $employee, array $dates, ?LeaveApplication $existing): bool
    {
        return LeaveApplicationDay::query()
            ->where('employee_id', $employee->id)
            ->whereIn('date', $dates)
            ->whereHas('application', fn ($q) => $q->active()->when($existing, fn ($q) => $q->whereKeyNot($existing->id)))
            ->exists();
    }
}
