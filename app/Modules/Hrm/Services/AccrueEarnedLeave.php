<?php

namespace App\Modules\Hrm\Services;

use App\Modules\Hrm\Data\EarnedPeriod;
use App\Modules\Hrm\Data\LeaveYear;
use App\Modules\Hrm\Enums\LeaveEarnType;
use App\Modules\Hrm\Enums\LeaveEntryType;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\LeaveAccrual;
use App\Modules\Hrm\Models\LeaveProfileItem;
use App\Modules\Hrm\Scopes\VisibleToCurrentUser;
use App\Modules\Payroll\Enums\AttendanceStatus;
use App\Modules\Payroll\Models\AttendanceDay;
use Carbon\CarbonImmutable;

/**
 * Credits earned leave for the current company: every night (hrm:accrue-leave, 03:00). [LV-05]
 *
 * For each employee with a leave profile, each ACTIVE leave type in it: LeaveEarning says which periods are
 * due; those without a leave_accruals row are credited (LeaveLedger::credit Earned, source = the accrual row).
 * A period that earns 0 days gets a row without a ledger entry. Safe to run any number of times.
 *
 * Company setting EARN_FROM ("Earn leave from", the go-live date, Leave types tab): nothing that started
 * before it is credited.
 */
class AccrueEarnedLeave
{
    public const EARN_FROM = 'leave.earn_from';

    public function __construct(
        private LeaveEarning $earning,
        private LeaveLedger $ledger,
    ) {}

    /**
     * @param  list<int>|null  $employeeIds  only these (null = everyone)
     * @return array{periods: int, days: float} what was credited
     */
    public function run(CarbonImmutable $today, ?array $employeeIds = null): array
    {
        $today = $today->startOfDay();
        $earnFrom = ($from = settings(self::EARN_FROM)) ? CarbonImmutable::parse($from) : null;
        $startMonth = LeaveYear::startMonth();
        $since = $today->subDays(LeaveEarning::LOOKBACK_DAYS);
        $done = ['periods' => 0, 'days' => 0.0];

        // Also people who left recently: their last month may still be due.
        $employees = Employee::query()->withoutGlobalScope(VisibleToCurrentUser::class)
            ->whereNotNull('leave_profile_id')
            ->whereDate('join_date', '<=', $today)
            ->where(fn ($q) => $q->whereNull('separation_date')->orWhereDate('separation_date', '>=', $since->startOfMonth()->subMonth()))
            ->when($employeeIds !== null, fn ($q) => $q->whereIn('id', $employeeIds))
            ->with('leaveProfile.items.leaveType')
            ->orderBy('id')
            ->get();

        foreach ($employees as $employee) {
            foreach ($employee->leaveProfile->items ?? [] as $item) {
                foreach ($this->dueFor($employee, $item, $today, $earnFrom, $startMonth) as $period) {
                    $this->credit($employee, $item, $period);
                    $done['periods']++;
                    $done['days'] += $period->days;
                }
            }
        }

        return $done;
    }

    /** @return list<EarnedPeriod> the due periods not credited yet */
    private function dueFor(Employee $employee, LeaveProfileItem $item, CarbonImmutable $today, ?CarbonImmutable $earnFrom, int $startMonth): array
    {
        $type = $item->leaveType;
        $perDays = $type->earn_type === LeaveEarnType::PerDaysWorked;
        if (! $type->is_active || (! $perDays && $item->days_per_year <= 0)) {
            return [];
        }

        $due = $this->earning->due(
            $type, $item->days_per_year, $employee->join_date->toImmutable(), $employee->separation_date?->toImmutable(),
            $today, $earnFrom, $startMonth,
            $perDays ? fn (CarbonImmutable $from, CarbonImmutable $to) => $this->workedDays($employee, $from, $to) : null,
        );
        if ($due === []) {
            return [];
        }

        $credited = LeaveAccrual::query()->where('employee_id', $employee->id)->where('leave_type_id', $type->id)
            ->whereIn('period_start', array_map(fn (EarnedPeriod $p) => $p->start->toDateString(), $due))
            ->pluck('period_start')->map(fn ($d) => CarbonImmutable::parse($d)->toDateString())->all();

        return array_values(array_filter($due, fn (EarnedPeriod $p) => ! in_array($p->start->toDateString(), $credited, true)));
    }

    private function credit(Employee $employee, LeaveProfileItem $item, EarnedPeriod $period): void
    {
        (new LeaveAccrual)->getConnection()->transaction(function () use ($employee, $item, $period) {
            $accrual = LeaveAccrual::create([
                'employee_id' => $employee->id,
                'leave_type_id' => $item->leave_type_id,
                'year' => $period->year,
                'period_start' => $period->start->toDateString(),
                'period_end' => $period->end->toDateString(),
                'days' => $period->days,
                'basis' => $period->basis,
            ]);

            if ($period->days > 0) {
                $entry = $this->ledger->credit($employee, $item->leaveType, LeaveYear::of($period->year), $period->days,
                    LeaveEntryType::Earned, $period->creditOn, source: $accrual, note: $period->basis);
                $accrual->update(['leave_ledger_id' => $entry->id]);
            }
        });
    }

    /** Processed attendance: present = 1, half day = ½. */
    private function workedDays(Employee $employee, CarbonImmutable $from, CarbonImmutable $to): float
    {
        $counts = AttendanceDay::query()->where('employee_id', $employee->id)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->whereIn('status', [AttendanceStatus::Present, AttendanceStatus::HalfDay])
            ->selectRaw('status, COUNT(*) as n')->groupBy('status')
            ->toBase()->pluck('n', 'status');

        return (float) ($counts[AttendanceStatus::Present->value] ?? 0) + 0.5 * (float) ($counts[AttendanceStatus::HalfDay->value] ?? 0);
    }
}
