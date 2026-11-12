<?php

namespace App\Modules\Hrm\Services;

use App\Modules\Hrm\Data\EarnedPeriod;
use App\Modules\Hrm\Data\LeaveYear;
use App\Modules\Hrm\Enums\LeaveEarnType;
use App\Modules\Hrm\Models\LeaveType;
use Carbon\CarbonImmutable;

/**
 * Which earning periods of one leave type are due for one employee, and how many days each earns. [LV-05]
 * PURE: no database, no clock (tests/Unit/LeaveEarningTest.php). AccrueEarnedLeave loads the inputs,
 * skips periods already credited (leave_accruals) and writes the rest.
 *
 * $perYear = days per year in the employee's leave profile. Employed = join date … separation date.
 *
 *   Fixed           the whole year's days, credited at the start of each leave year (or on the join date)
 *   Prorata         month by month, AFTER the month: perYear × days employed in the month ÷ days in the leave year
 *   MonthlyAccrual  month by month, at the START of the month, same formula (legacy "Accrued leave")
 *   PerDaysWorked   month by month, after the month: days worked (attendance: present 1, half day ½) ÷ N
 *   Anniversary     the year's days on each work anniversary (1 year, 2 years …)
 *
 * Which periods are returned (credit date up to $today):
 *   - catch-up: credit date in the last LOOKBACK_DAYS, so missed nights are made up but years of history are
 *     never back-filled; Fixed also catches up the whole CURRENT leave year (a company going live mid-year)
 *   - $earnFrom (company setting "Earn leave from", the go-live date): only periods STARTING on or after it,
 *     because the opening balances entered at go-live already contain what was earned before
 */
class LeaveEarning
{
    public const LOOKBACK_DAYS = 62;

    /**
     * @param  callable(CarbonImmutable, CarbonImmutable): float|null  $workedDays  days worked between two dates (PerDaysWorked)
     * @return list<EarnedPeriod>
     */
    public function due(
        LeaveType $type,
        float $perYear,
        CarbonImmutable $join,
        ?CarbonImmutable $separation,
        CarbonImmutable $today,
        ?CarbonImmutable $earnFrom,
        int $startMonth,
        ?callable $workedDays = null,
    ): array {
        $since = $today->subDays(self::LOOKBACK_DAYS);
        $current = LeaveYear::containing($today, $startMonth)->year;

        $periods = match ($type->earn_type) {
            LeaveEarnType::Fixed => $this->fixed($perYear, $join, $separation, $since, $today, $startMonth),
            LeaveEarnType::Anniversary => $this->anniversaries($perYear, $join, $separation, $since, $today, $startMonth),
            LeaveEarnType::Prorata, LeaveEarnType::MonthlyAccrual, LeaveEarnType::PerDaysWorked
                => $this->months($type, $perYear, $join, $separation, $since, $today, $startMonth, $workedDays),
        };

        return array_values(array_filter($periods, fn (EarnedPeriod $p) => $p->creditOn->lte($today)
            && ($p->creditOn->gte($since) || ($type->earn_type === LeaveEarnType::Fixed && $p->year === $current))
            && ($earnFrom === null || $p->start->gte($earnFrom))));
    }

    /** @return list<EarnedPeriod> */
    private function fixed(float $perYear, CarbonImmutable $join, ?CarbonImmutable $separation, CarbonImmutable $since, CarbonImmutable $today, int $startMonth): array
    {
        $periods = [];
        for ($year = LeaveYear::containing($since, $startMonth); $year->start()->lte($today); $year = $year->next()) {
            if ($join->gt($year->end()) || ($separation !== null && $separation->lt($year->start()))) {
                continue;
            }
            $periods[] = new EarnedPeriod($year->start(), $year->end(), $join->max($year->start()), $year->year, round($perYear, 2),
                __('Leave year :year', ['year' => $year->label()]));
        }

        return $periods;
    }

    /** @return list<EarnedPeriod> */
    private function anniversaries(float $perYear, CarbonImmutable $join, ?CarbonImmutable $separation, CarbonImmutable $since, CarbonImmutable $today, int $startMonth): array
    {
        $periods = [];
        for ($n = max(1, $since->year - $join->year - 1); ($date = $join->addYearsNoOverflow($n))->lte($today); $n++) {
            if ($separation !== null && $date->gt($separation)) {
                break;
            }
            $periods[] = new EarnedPeriod($date, $date, $date, LeaveYear::containing($date, $startMonth)->year, round($perYear, 2),
                trans_choice('Work anniversary (:n year)|Work anniversary (:n years)', $n, ['n' => $n]));
        }

        return $periods;
    }

    /** @return list<EarnedPeriod> */
    private function months(LeaveType $type, float $perYear, CarbonImmutable $join, ?CarbonImmutable $separation, CarbonImmutable $since, CarbonImmutable $today, int $startMonth, ?callable $workedDays): array
    {
        $advance = $type->earn_type === LeaveEarnType::MonthlyAccrual;
        $periods = [];

        // From the month before $since (its arrears may still be due) to this month.
        for ($month = $since->startOfMonth()->subMonth(); $month->lte($today); $month = $month->addMonth()) {
            $end = $month->endOfMonth()->startOfDay();
            $from = $join->max($month);
            $to = $separation === null ? $end : $separation->min($end);
            if ($from->gt($to)) {
                continue;   // not employed in this month
            }

            $year = LeaveYear::containing($month, $startMonth);
            $label = $month->translatedFormat('M Y');
            $creditOn = $advance ? $from : $end->addDay();

            if ($type->earn_type === LeaveEarnType::PerDaysWorked) {
                $perDays = (float) $type->earn_per_days_worked;
                $worked = $workedDays !== null && $perDays > 0 ? $workedDays($from, $to) : 0.0;
                $days = $perDays > 0 ? round($worked / $perDays, 2) : 0.0;
                $basis = __(':worked days worked in :month ÷ :n', ['worked' => $worked, 'month' => $label, 'n' => $perDays]);
            } else {
                $employed = (int) $from->diffInDays($to) + 1;
                $daysInYear = (int) $year->start()->diffInDays($year->end()) + 1;
                $days = round($perYear * $employed / $daysInYear, 2);
                $basis = __(':employed of :total days employed in :month (:per a year)', [
                    'employed' => $employed, 'total' => (int) $month->diffInDays($end) + 1, 'month' => $label, 'per' => $perYear,
                ]);
            }

            $periods[] = new EarnedPeriod($month, $end, $creditOn, $year->year, $days, $basis);
        }

        return $periods;
    }
}
