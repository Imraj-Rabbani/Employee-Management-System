<?php

namespace App\Modules\Hrm\Services;

use App\Modules\Hrm\Data\LeaveYear;
use App\Modules\Hrm\Models\LeaveApplication;
use App\Modules\Hrm\Models\LeaveBalance;
use App\Modules\Hrm\Scopes\VisibleToCurrentUser;
use Carbon\CarbonImmutable;

/**
 * Leave year end for the current company: every night (hrm:carry-forward-leave, 03:30), after the accrual. [LV-05]
 *
 *   1. Once the previous leave year ended more than CLOSE_AFTER_DAYS ago (company setting, default 15, so leave
 *      of late December approved in early January still lands in its year): every balance of that year is
 *      closed by LeaveLedger::closeYear() (carry forward up to the type's limit, the rest expires).
 *      An employee + type with a leave application of that year still WAITING is skipped until it is decided.
 *   2. Carried-forward days whose expiry date has come expire (LeaveLedger::expireCarriedForward()).
 *
 * Both steps are idempotent: closeYear() runs once per year, expiry once per carried row.
 * Once a year is closed (isClosed()), no new leave can be applied for in it (ApplyForLeave).
 */
class CarryForwardLeave
{
    public const CLOSE_AFTER_DAYS = 'leave.close_after_days';

    public const DEFAULT_CLOSE_AFTER_DAYS = 15;

    public function __construct(private LeaveLedger $ledger) {}

    /** @return array{closed: int, waiting: int, carried: float, expired: int} */
    public function run(CarbonImmutable $today): array
    {
        $today = $today->startOfDay();
        $result = ['closed' => 0, 'waiting' => 0, 'carried' => 0.0, 'expired' => 0];

        $previous = LeaveYear::containing($today)->previous();
        if (self::isClosed($previous, $today)) {
            $balances = LeaveBalance::query()->where('year', $previous->year)->with([
                // System work: every employee, whoever (if anyone) is logged in.
                'employee' => fn ($q) => $q->withoutGlobalScope(VisibleToCurrentUser::class),
                'leaveType',
            ])->orderBy('id')->get();

            foreach ($balances as $balance) {
                if ($balance->employee === null || $balance->leaveType === null) {
                    continue;
                }
                $waiting = LeaveApplication::query()->active()->whereNull('approved_at')
                    ->where('employee_id', $balance->employee_id)->where('leave_type_id', $balance->leave_type_id)
                    ->where('leave_year', $previous->year)->exists();
                if ($waiting) {
                    $result['waiting']++;

                    continue;
                }

                $result['carried'] += $this->ledger->closeYear($balance->employee, $balance->leaveType, $previous);
                $result['closed']++;
            }
        }

        $result['expired'] = $this->ledger->expireCarriedForward($today);

        return $result;
    }

    /** A leave year is closed CLOSE_AFTER_DAYS days after its last day. */
    public static function isClosed(LeaveYear $year, CarbonImmutable $today): bool
    {
        return $today->startOfDay()->gt($year->end()->addDays(self::closeAfterDays()));
    }

    public static function closeAfterDays(): int
    {
        return max(0, (int) settings(self::CLOSE_AFTER_DAYS, self::DEFAULT_CLOSE_AFTER_DAYS));
    }
}
