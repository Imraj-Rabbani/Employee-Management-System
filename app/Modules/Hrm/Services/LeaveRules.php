<?php

namespace App\Modules\Hrm\Services;

use App\Modules\Hrm\Data\LeaveCheck;
use App\Modules\Hrm\Data\LeaveProblem;
use App\Modules\Hrm\Data\LeaveRequest;
use App\Modules\Hrm\Enums\LeaveBridging;
use App\Modules\Hrm\Enums\LeaveEligibleFrom;
use App\Modules\Hrm\Models\LeaveType;
use Carbon\CarbonImmutable;

/**
 * Judges one leave request against the rules of its leave type (8.1). [LV-01] PURE: no database, no clock
 * (today comes in the request), so every rule has its own unit test (tests/Unit/LeaveRulesTest.php).
 *
 *     $check = app(LeaveRules::class)->check($type, $request);     // LeaveRequestInputs builds $request
 *     $check->ok(), $check->days, $check->problems
 *
 * Counting the days:
 *   - every date from–to counts 1; a half day counts 0.5 (from = to)
 *   - "Do not count weekends and holidays": days off inside the leave count 0
 *   - sandwich rule (bridging): the run of days off right before / after the leave is added to it;
 *     one-sided = the shorter run (legacy), two-sided = both
 *
 * ALL problems are collected (not only the first), so the person sees everything to fix at once.
 * Exception: impossible dates stop the check (nothing else can be judged).
 *
 * Not here: the leave year split (a leave over two leave years, 8.4) and the approval (Workflow).
 */
class LeaveRules
{
    /** How far the sandwich rule looks for days off before / after the leave. */
    public const BRIDGE_LOOKAROUND = 31;

    public function check(LeaveType $type, LeaveRequest $request): LeaveCheck
    {
        if ($request->to->lt($request->from)) {
            return new LeaveCheck(0, [], 0, 0, [new LeaveProblem('dates', 'to_date', __('The last day is before the first day.'))]);
        }

        $problems = [];
        $add = function (string $code, string $field, string $message) use (&$problems): void {
            $problems[] = new LeaveProblem($code, $field, $message);
        };

        [$dates, $bridged] = $this->count($type, $request);
        $days = array_sum($dates);
        $calendarDays = (int) $request->from->diffInDays($request->to) + 1;

        $this->employment($request, $add);
        $this->eligibility($type, $request, $add);
        $this->application($type, $request, $days, $calendarDays, $add);
        $this->timing($type, $request, $add);
        $this->documents($type, $request, $days, $add);
        $this->balance($type, $request, $days, $add);

        return new LeaveCheck($days, $dates, $calendarDays, $bridged, $problems);
    }

    /**
     * @return array{0: array<string, float>, 1: int} the counted dates and how many were added by bridging
     */
    private function count(LeaveType $type, LeaveRequest $request): array
    {
        $counts = fn (CarbonImmutable $d) => ! ($type->exclude_holidays && isset($request->daysOff[$d->toDateString()]));

        if ($request->halfDay) {
            return [$counts($request->from) ? [$request->from->toDateString() => 0.5] : [], 0];
        }

        $dates = [];
        for ($d = $request->from; $d->lte($request->to); $d = $d->addDay()) {
            if ($counts($d)) {
                $dates[$d->toDateString()] = 1.0;
            }
        }

        $bridge = $this->bridge($type->bridging, $request);
        foreach ($bridge as $date) {
            $dates[$date] = 1.0;
        }
        ksort($dates);

        return [$dates, count($bridge)];
    }

    /** @return list<string> the days off the sandwich rule adds */
    private function bridge(LeaveBridging $bridging, LeaveRequest $request): array
    {
        if ($bridging === LeaveBridging::None) {
            return [];
        }

        $before = $this->runOfDaysOff($request, $request->from->subDay(), -1);
        $after = $this->runOfDaysOff($request, $request->to->addDay(), 1);

        if ($bridging === LeaveBridging::TwoSided) {
            return [...$before, ...$after];
        }
        // One-sided, as legacy: the shorter side; before wins a tie; a side without days off is never chosen.
        if ($before === [] || $after === []) {
            return $before === [] ? $after : $before;
        }

        return count($before) <= count($after) ? $before : $after;
    }

    /** @return list<string> consecutive days off starting at $start, walking $step days at a time */
    private function runOfDaysOff(LeaveRequest $request, CarbonImmutable $start, int $step): array
    {
        $run = [];
        for ($d = $start, $i = 0; $i < self::BRIDGE_LOOKAROUND && isset($request->daysOff[$d->toDateString()]); $d = $d->addDays($step), $i++) {
            $run[] = $d->toDateString();
        }

        return $run;
    }

    /** @param  callable(string, string, string): void  $add */
    private function employment(LeaveRequest $request, callable $add): void
    {
        if ($request->joinDate !== null && $request->from->lt($request->joinDate)) {
            $add('before_joining', 'from_date', __('The leave starts before the join date (:date).', ['date' => format_date($request->joinDate)]));
        }
        if ($request->separationDate !== null && $request->to->gt($request->separationDate)) {
            $add('after_separation', 'to_date', __('The leave ends after the last working day (:date).', ['date' => format_date($request->separationDate)]));
        }
        if ($request->overlaps) {
            $add('overlap', 'from_date', __('Another leave already covers some of these days.'));
        }
    }

    /** @param  callable(string, string, string): void  $add */
    private function eligibility(LeaveType $type, LeaveRequest $request, callable $add): void
    {
        if ($request->entitlement === null) {
            $add('not_in_profile', 'leave_type_id', __(':type is not in this employee\'s leave profile.', ['type' => $type->name]));
        }
        if ($type->hr_only && ! $request->byHr) {
            $add('hr_only', 'leave_type_id', __(':type can only be entered by HR.', ['type' => $type->name]));
        }
        if ($type->gender !== null && $request->gender !== $type->gender) {
            $add('gender', 'leave_type_id', __(':type is only for :who employees.', ['type' => $type->name, 'who' => $type->gender->label()]));
        }
        if ($type->religion !== null && $request->religion !== $type->religion) {
            $add('religion', 'leave_type_id', __(':type is only for :who employees.', ['type' => $type->name, 'who' => $type->religion->label()]));
        }

        $since = $type->eligible_from === LeaveEligibleFrom::ConfirmationDate ? $request->confirmationDate : $request->joinDate;
        if ($type->eligible_from === LeaveEligibleFrom::ConfirmationDate && $since === null) {
            $add('not_confirmed', 'leave_type_id', __(':type is only for confirmed employees.', ['type' => $type->name]));
        } elseif ($since !== null && $type->eligible_after_months > 0 && $request->from->lt($eligible = $since->addMonths($type->eligible_after_months))) {
            $add('too_early', 'from_date', __(':type can be taken from :date (:months months after the :start).', [
                'type' => $type->name, 'date' => format_date($eligible), 'months' => $type->eligible_after_months,
                'start' => mb_strtolower($type->eligible_from->label()),
            ]));
        }

        if ($type->max_times_in_service !== null && $request->timesTaken >= $type->max_times_in_service) {
            $add('max_times', 'leave_type_id', __(':type can be taken :n times in the whole service; it was already taken :taken times.', [
                'type' => $type->name, 'n' => $type->max_times_in_service, 'taken' => $request->timesTaken,
            ]));
        }
    }

    /** @param  callable(string, string, string): void  $add */
    private function application(LeaveType $type, LeaveRequest $request, float $days, int $calendarDays, callable $add): void
    {
        if ($request->halfDay) {
            if (! $type->allow_half_day) {
                $add('half_day', 'is_half_day', __(':type cannot be taken as a half day.', ['type' => $type->name]));
            }
            if (! $request->from->isSameDay($request->to)) {
                $add('half_day_range', 'is_half_day', __('A half day is one date: the first and last day must be the same.'));
            }
        }

        if ($days <= 0) {
            $add('no_days', 'from_date', __('These dates are all days off: there is nothing to take leave for.'));

            return;
        }
        if ($type->min_days !== null && $days < $type->min_days) {
            $add('min_days', 'to_date', __(':type is at least :n days at a time.', ['type' => $type->name, 'n' => $type->min_days]));
        }
        if ($type->max_days !== null && $days > $type->max_days) {
            $add('max_days', 'to_date', __(':type is at most :n days at a time (this is :days).', ['type' => $type->name, 'n' => $type->max_days, 'days' => $days]));
        }
        if ($type->max_calendar_days !== null && $calendarDays > $type->max_calendar_days) {
            $add('max_calendar_days', 'to_date', __(':type can span at most :n calendar days, weekends included (this is :days).', [
                'type' => $type->name, 'n' => $type->max_calendar_days, 'days' => $calendarDays,
            ]));
        }
    }

    /** Notice before / applying afterwards. HR is not held to these. @param  callable(string, string, string): void  $add */
    private function timing(LeaveType $type, LeaveRequest $request, callable $add): void
    {
        if ($request->byHr) {
            return;
        }

        if ($request->from->lt($request->today)) {
            if (! $type->allow_post_facto) {
                $add('post_facto', 'from_date', __(':type must be applied for before it starts.', ['type' => $type->name]));
            } elseif ($type->post_facto_days !== null && $request->from->diffInDays($request->today) > $type->post_facto_days) {
                $add('post_facto_days', 'from_date', __(':type can be applied for at most :n days after it started.', ['type' => $type->name, 'n' => $type->post_facto_days]));
            }

            return;
        }

        if ($type->notice_days > 0 && $request->from->lt($earliest = $request->today->addDays($type->notice_days))) {
            $add('notice', 'from_date', __(':type must be applied for :n days ahead: the earliest start is :date.', [
                'type' => $type->name, 'n' => $type->notice_days, 'date' => format_date($earliest),
            ]));
        }
    }

    /** @param  callable(string, string, string): void  $add */
    private function documents(LeaveType $type, LeaveRequest $request, float $days, callable $add): void
    {
        if ($type->reason_required && ! $request->hasReason) {
            $add('reason', 'reason', __('Please give a reason for :type.', ['type' => $type->name]));
        }
        if ($type->attachment_after_days !== null && $days > $type->attachment_after_days && ! $request->hasAttachment) {
            $add('attachment', 'attachment', $type->attachment_after_days == 0
                ? __(':type needs an attachment.', ['type' => $type->name])
                : __(':type for more than :n days needs an attachment (e.g. a medical certificate).', ['type' => $type->name, 'n' => $type->attachment_after_days]));
        }
    }

    /** @param  callable(string, string, string): void  $add */
    private function balance(LeaveType $type, LeaveRequest $request, float $days, callable $add): void
    {
        if (! $type->requires_balance || $days <= 0) {
            return;
        }

        // "Maximum balance": no more than this can ever be used, whatever the ledger says.
        $available = $type->max_balance !== null ? min($request->balance, $type->max_balance) : $request->balance;
        if ($days > $available) {
            $add('balance', 'to_date', __('Not enough :type left: :available days available, :days asked.', [
                'type' => $type->name, 'available' => max(0, $available), 'days' => $days,
            ]));
        }
    }
}
