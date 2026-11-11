<?php

namespace App\Modules\Payroll\Actions;

use App\Modules\Hrm\Models\Employee;
use App\Modules\Payroll\Models\AttendanceCorrection;
use App\Modules\Payroll\Models\AttendanceDay;
use App\Modules\Payroll\Models\Shift;
use App\Modules\Payroll\Services\AttendanceReprocessor;
use App\Modules\Workflow\Enums\ApprovalStatus;
use App\Modules\Workflow\Exceptions\WorkflowException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Asks for an attendance correction (new, or changed after it was sent back) and sends it for approval. [TA-06]
 * The ONE way corrections are written: by HR (Corrections tab) and by the employee (Self Service).
 *
 *     $values = ['date' => '2026-12-03', 'in' => '09:00' | '', 'out' => '18:00' | '', 'reason' => 'Forgot my card'];
 *     if ($problem = $action->problem($employee, $values, self: true)) { show it } else { $action->handle($employee, $values) }
 *
 * Rules for everyone: in and / or out; not a future day; employed that day; day not locked (payroll closed);
 * one open correction per employee and day. Self Service only (company settings, 0 = no limit):
 *   attendance.correction_days_back       how many days back an employee may ask
 *   attendance.correction_monthly_limit   how many corrections per month (rejected ones do not count)
 *
 * Out at or before in = the next morning (overnight shift). Only out given: the next morning when the
 * day's shift is overnight and out is before its start.
 */
class RequestCorrection
{
    public const DAYS_BACK = 'attendance.correction_days_back';

    public const MONTHLY_LIMIT = 'attendance.correction_monthly_limit';

    /**
     * Why this correction cannot be asked, in words for the user, or null when all is well.
     *
     * @param  array{date: string, in: string, out: string, reason: string}  $values
     */
    public function problem(Employee $employee, array $values, bool $self, ?AttendanceCorrection $existing = null): ?string
    {
        $date = $values['date'];
        $today = app(AttendanceReprocessor::class)->today();

        if ($existing !== null && ! $existing->isEditable()) {
            return __('This correction is waiting for approval or already decided, so it cannot be changed.');
        }
        if ($values['in'] === '' && $values['out'] === '') {
            return __('Give the in time, the out time or both.');
        }
        if ($date > $today) {
            return __('A correction can only be for today or an earlier day.');
        }
        [, $out] = $this->times($employee, $values);
        if ($out !== null && $out->isFuture()) {
            return __('The out time has not come yet.');
        }
        if ($employee->join_date->toDateString() > $date || ($employee->separation_date && $employee->separation_date->toDateString() < $date)) {
            return __(':name was not employed on :date.', ['name' => $employee->name, 'date' => format_date($date)]);
        }
        if (AttendanceDay::query()->where('employee_id', $employee->id)->whereDate('date', $date)->where('is_locked', true)->exists()) {
            return __('The payroll for :date is closed: this day cannot be corrected any more.', ['date' => format_date($date)]);
        }

        $others = AttendanceCorrection::query()->with('approvals')
            ->where('employee_id', $employee->id)
            ->when($existing, fn ($q) => $q->whereKeyNot($existing->id));
        $open = (clone $others)->whereDate('date', $date)->get()->first(fn (AttendanceCorrection $c) => $c->isOpen());
        if ($open !== null) {
            return __(':no for this day is still open. Wait for its answer, or change it when it is sent back.', ['no' => $open->number()]);
        }

        if ($self) {
            $daysBack = (int) settings(self::DAYS_BACK, 0);
            if ($daysBack > 0 && $date < Carbon::parse($today)->subDays($daysBack)->toDateString()) {
                return trans_choice('A correction can be asked at most :count day back. Ask HR for older days.|A correction can be asked at most :count days back. Ask HR for older days.', $daysBack);
            }

            $limit = (int) settings(self::MONTHLY_LIMIT, 0);
            $month = Carbon::parse($date);
            $used = (clone $others)->whereBetween('date', [$month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString()])
                ->get()->reject(fn (AttendanceCorrection $c) => $c->approvalStatus() === ApprovalStatus::Rejected)->count();
            if ($limit > 0 && $used >= $limit) {
                return trans_choice('You can ask at most :count correction per month, and :month already has it.|You can ask at most :count corrections per month, and :month already has them.', $limit, ['month' => $month->translatedFormat('F')]);
            }
        }

        return null;
    }

    /**
     * Saves the correction and sends it for approval (again, when it was sent back).
     * A NEW correction that nobody can approve (no workflow) is deleted again and the WorkflowException
     * (message for the user) is thrown on.
     *
     * @param  array{date: string, in: string, out: string, reason: string}  $values
     *
     * @throws WorkflowException
     */
    public function handle(Employee $employee, array $values, ?AttendanceCorrection $existing = null): AttendanceCorrection
    {
        [$in, $out] = $this->times($employee, $values);
        $attributes = [
            'employee_id' => $employee->id,
            'date' => $values['date'],
            'requested_in_at' => $in,
            'requested_out_at' => $out,
            'reason' => trim($values['reason']),
        ];

        if ($existing !== null) {
            $existing->update($attributes);
            $existing->submitForApproval();

            return $existing;
        }

        $correction = AttendanceCorrection::create([...$attributes, 'requested_by_user_id' => Auth::id()]);
        try {
            $correction->submitForApproval();
        } catch (WorkflowException $e) {
            $correction->delete();   // never keep a correction that nobody can approve

            throw $e;
        }

        return $correction;
    }

    /**
     * The requested times as moments: [in, out], null = keep the punch.
     *
     * @param  array{date: string, in: string, out: string, reason: string}  $values
     * @return array{0: Carbon|null, 1: Carbon|null}
     */
    public function times(Employee $employee, array $values): array
    {
        $in = $values['in'] === '' ? null : Carbon::parse($values['date'].' '.$values['in']);
        $out = $values['out'] === '' ? null : Carbon::parse($values['date'].' '.$values['out']);

        if ($out !== null) {
            $nextMorning = $in !== null
                ? $out->lte($in)
                : ($shift = $this->shiftOn($employee, $values['date'])) !== null && $shift->is_overnight && $out->format('H:i') < substr($shift->start_time, 0, 5);
            if ($nextMorning) {
                $out = $out->addDay();
            }
        }

        return [$in, $out];
    }

    /** The day's shift: as processed, else the employee's own, else the default. */
    private function shiftOn(Employee $employee, string $date): ?Shift
    {
        $processed = AttendanceDay::query()->where('employee_id', $employee->id)->whereDate('date', $date)->value('shift_id');
        $id = $processed ?? $employee->shift_id;

        return ($id ? Shift::find($id) : null) ?? Shift::default();
    }
}
