<?php

namespace App\Modules\Payroll\Actions;

use App\Modules\Hrm\Models\Employee;
use App\Modules\Payroll\Models\AttendanceDay;
use App\Modules\Payroll\Models\OvertimeRequest;
use App\Modules\Payroll\Models\Shift;
use App\Modules\Payroll\Services\AttendanceReprocessor;
use App\Modules\Workflow\Exceptions\WorkflowException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Asks for overtime (new, or changed after it was sent back) and sends it for approval. [TA-07]
 * The ONE way overtime requests are written: by HR / managers (Overtime tab) and by the employee (Self Service).
 *
 *     $values = ['date' => '2026-12-03', 'hours' => '2.5', 'detail' => 'Month-end closing'];
 *     if ($problem = $action->problem($employee, $values)) { show it } else { $action->handle($employee, $values) }
 *
 * Rules: 0 < hours ≤ 24 in quarter hours; employed that day; at most DAYS_AHEAD days ahead (planned overtime);
 * day not locked (payroll closed); one request per employee and day (rejected ones aside).
 */
class RequestOvertime
{
    /** Overtime may be asked before it is worked, up to this many days ahead. */
    public const DAYS_AHEAD = 31;

    /** @param  array{date: string, hours: string, detail: string}  $values */
    public function problem(Employee $employee, array $values, ?OvertimeRequest $existing = null): ?string
    {
        $date = $values['date'];
        $hours = (float) $values['hours'];

        if ($existing !== null && ! $existing->isEditable()) {
            return __('This request is waiting for approval or already decided, so it cannot be changed.');
        }
        if ($hours <= 0 || $hours > OvertimeRequest::MAX_HOURS || fmod($hours * 4, 1.0) !== 0.0) {
            return __('Hours: more than 0 and at most :max, in quarter hours (e.g. 1.25).', ['max' => OvertimeRequest::MAX_HOURS]);
        }
        if ($date > Carbon::parse(app(AttendanceReprocessor::class)->today())->addDays(self::DAYS_AHEAD)->toDateString()) {
            return __('Overtime can be asked at most :days days ahead.', ['days' => self::DAYS_AHEAD]);
        }
        if ($employee->join_date->toDateString() > $date || ($employee->separation_date && $employee->separation_date->toDateString() < $date)) {
            return __(':name was not employed on :date.', ['name' => $employee->name, 'date' => format_date($date)]);
        }
        if (AttendanceDay::query()->where('employee_id', $employee->id)->whereDate('date', $date)->where('is_locked', true)->exists()) {
            return __('The payroll for :date is closed: no more overtime for this day.', ['date' => format_date($date)]);
        }

        $other = OvertimeRequest::query()->with('approvals')
            ->where('employee_id', $employee->id)->whereDate('date', $date)
            ->when($existing, fn ($q) => $q->whereKeyNot($existing->id))
            ->get()->first(fn (OvertimeRequest $r) => $r->isActive());
        if ($other !== null) {
            return __(':no already asks overtime for this day.', ['no' => $other->number()]);
        }

        return null;
    }

    /**
     * Saves the request and sends it for approval (again, when it was sent back). A NEW request that nobody
     * can approve is deleted again and the WorkflowException (message for the user) is thrown on.
     *
     * @param  array{date: string, hours: string, detail: string}  $values
     *
     * @throws WorkflowException
     */
    public function handle(Employee $employee, array $values, ?OvertimeRequest $existing = null): OvertimeRequest
    {
        $attributes = [
            'employee_id' => $employee->id,
            'date' => $values['date'],
            'shift_id' => Shift::ofEmployeeOn($employee, $values['date'])?->id,
            'hours' => round((float) $values['hours'], 2),
            'detail' => trim($values['detail']) ?: null,
        ];

        if ($existing !== null) {
            $existing->update($attributes);
            $existing->submitForApproval();

            return $existing;
        }

        $request = OvertimeRequest::create([...$attributes, 'requested_by_user_id' => Auth::id()]);
        try {
            $request->submitForApproval();
        } catch (WorkflowException $e) {
            $request->delete();   // never keep a request that nobody can approve

            throw $e;
        }

        return $request;
    }
}
