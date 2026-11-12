<?php

namespace App\Modules\Hrm\Services;

use App\Modules\Hrm\Contracts\WorkCalendar;
use App\Modules\Hrm\Data\LeaveRequest;
use App\Modules\Hrm\Data\LeaveYear;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\LeaveType;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Collects from the database what LeaveRules needs for one request (like AttendanceInputs → AttendanceProcessor).
 *
 *     $request = $inputs->build($employee, $type, $from, $to, timesTaken: 0, overlaps: false);
 *     $check = $rules->check($type, $request);
 *
 * Loaded here: the employee's gender / religion / dates, their profile's days for this type, the balance in
 * the leave year of $from (LeaveLedger), and the days off around the leave (WorkCalendar).
 * NOT loaded here: $timesTaken and $overlaps, because they come from leave_applications (8.4): the caller
 * (ApplyForLeave) passes them. They are required arguments on purpose, so no caller forgets them.
 */
class LeaveRequestInputs
{
    public function __construct(
        private WorkCalendar $calendar,
        private LeaveLedger $ledger,
    ) {}

    public function build(
        Employee $employee,
        LeaveType $type,
        CarbonInterface $from,
        CarbonInterface $to,
        int $timesTaken,
        bool $overlaps,
        bool $halfDay = false,
        bool $hasReason = false,
        bool $hasAttachment = false,
        bool $byHr = false,
        ?CarbonInterface $today = null,
    ): LeaveRequest {
        $from = CarbonImmutable::parse($from)->startOfDay();
        $to = CarbonImmutable::parse($to)->startOfDay();
        $today = $today ? CarbonImmutable::parse($today)->startOfDay() : CarbonImmutable::now(settings('timezone', config('app.timezone')))->startOfDay();

        $entitlement = $employee->leaveProfile?->items()->where('leave_type_id', $type->id)->value('days_per_year');
        $around = LeaveRules::BRIDGE_LOOKAROUND;

        return new LeaveRequest(
            from: $from,
            to: $to,
            today: $today,
            halfDay: $halfDay,
            daysOff: $to->gte($from) ? $this->calendar->daysOff($employee, $from->subDays($around), $to->addDays($around)) : [],
            gender: $employee->gender,
            religion: $employee->religion,
            joinDate: $employee->join_date?->toImmutable(),
            confirmationDate: $employee->confirmation_date?->toImmutable(),
            separationDate: $employee->separation_date?->toImmutable(),
            entitlement: $entitlement === null ? null : (float) $entitlement,
            balance: $this->ledger->balance($employee, $type, LeaveYear::containing($from)),
            timesTaken: $timesTaken,
            overlaps: $overlaps,
            hasReason: $hasReason,
            hasAttachment: $hasAttachment,
            byHr: $byHr,
        );
    }
}
