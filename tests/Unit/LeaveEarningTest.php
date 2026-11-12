<?php

namespace Tests\Unit;

use App\Modules\Hrm\Data\EarnedPeriod;
use App\Modules\Hrm\Enums\LeaveEarnType;
use App\Modules\Hrm\Models\LeaveType;
use App\Modules\Hrm\Services\LeaveEarning;
use Carbon\CarbonImmutable;
use Tests\TestCase;

/**
 * Task 8.5: which earning periods are due and what they earn, per earn type. No database. [LV-05]
 * 12 days a year unless said otherwise; calendar leave year (start month 1) unless said otherwise.
 */
class LeaveEarningTest extends TestCase
{
    // ---------------------------------------------------------------- Fixed

    public function test_fixed_credits_the_whole_year_at_its_start_even_when_caught_up_later(): void
    {
        $due = $this->due(LeaveEarnType::Fixed, today: '2026-11-12');

        $this->assertSame([['2026-01-01', '2026-01-01', 2026, 12.0]], $this->summary($due));
    }

    public function test_fixed_for_someone_who_joined_during_the_year(): void
    {
        $due = $this->due(LeaveEarnType::Fixed, today: '2026-11-12', join: '2026-03-10');

        $this->assertSame('2026-03-10', $due[0]->creditOn->toDateString());
        $this->assertSame(12.0, $due[0]->days);
    }

    public function test_fixed_in_a_new_year_does_not_go_back_to_last_year(): void
    {
        $this->assertSame([2027], array_map(fn (EarnedPeriod $p) => $p->year, $this->due(LeaveEarnType::Fixed, today: '2027-01-01')));
    }

    public function test_fixed_with_a_leave_year_starting_in_july(): void
    {
        $due = $this->due(LeaveEarnType::Fixed, today: '2026-11-12', startMonth: 7);

        $this->assertSame([['2026-07-01', '2026-07-01', 2026, 12.0]], $this->summary($due));
    }

    public function test_nothing_before_the_go_live_date(): void
    {
        $this->assertSame([], $this->due(LeaveEarnType::Fixed, today: '2026-11-12', earnFrom: '2026-06-01'));
    }

    // ---------------------------------------------------------------- Prorata (after the month)

    public function test_prorata_credits_each_month_after_it_ended(): void
    {
        $due = $this->due(LeaveEarnType::Prorata, today: '2026-12-01');

        // Catch-up window of 62 days: September (credited 1 Oct), October (1 Nov), November (1 Dec). December is not over.
        $this->assertSame([
            ['2026-09-01', '2026-10-01', 2026, 0.99],   // 12 × 30 / 365
            ['2026-10-01', '2026-11-01', 2026, 1.02],   // 12 × 31 / 365
            ['2026-11-01', '2026-12-01', 2026, 0.99],
        ], $this->summary($due));
        $this->assertSame('30 of 30 days employed in Nov 2026 (12 a year)', $due[2]->basis);
    }

    public function test_prorata_for_part_of_a_month(): void
    {
        $joined = $this->due(LeaveEarnType::Prorata, today: '2026-12-01', join: '2026-11-16');
        $this->assertSame([['2026-11-01', '2026-12-01', 2026, 0.49]], $this->summary($joined));   // 15 days

        $left = $this->due(LeaveEarnType::Prorata, today: '2026-12-01', separation: '2026-11-10');
        $this->assertSame(0.33, end($left)->days);   // 10 days of November
    }

    public function test_go_live_skips_months_that_started_before_it(): void
    {
        $due = $this->due(LeaveEarnType::Prorata, today: '2026-12-01', earnFrom: '2026-10-15');

        $this->assertSame(['2026-11-01'], array_map(fn (EarnedPeriod $p) => $p->start->toDateString(), $due));
    }

    // ---------------------------------------------------------------- Monthly accrual (in advance)

    public function test_monthly_accrual_credits_at_the_start_of_the_month(): void
    {
        $due = $this->due(LeaveEarnType::MonthlyAccrual, today: '2026-12-01');

        $last = end($due);
        $this->assertSame(['2026-12-01', '2026-12-01', 2026, 1.02], [$last->start->toDateString(), $last->creditOn->toDateString(), $last->year, $last->days]);
    }

    // ---------------------------------------------------------------- Per days worked

    public function test_per_days_worked_divides_the_days_worked(): void
    {
        $asked = [];
        $due = $this->due(LeaveEarnType::PerDaysWorked, today: '2026-12-01', perDays: 18,
            worked: function (CarbonImmutable $from, CarbonImmutable $to) use (&$asked) {
                $asked[] = $from->toDateString().'…'.$to->toDateString();

                return 19.5;
            });

        $this->assertSame(1.08, end($due)->days);   // 19.5 ÷ 18
        $this->assertSame('19.5 days worked in Nov 2026 ÷ 18', end($due)->basis);
        $this->assertContains('2026-11-01…2026-11-30', $asked);
    }

    // ---------------------------------------------------------------- Anniversary

    public function test_anniversary(): void
    {
        $this->assertSame([['2026-11-20', '2026-11-20', 2026, 12.0]],
            $this->summary($this->due(LeaveEarnType::Anniversary, today: '2026-11-20', join: '2023-11-20')));
        $this->assertSame('Work anniversary (3 years)', $this->due(LeaveEarnType::Anniversary, today: '2026-11-20', join: '2023-11-20')[0]->basis);

        $this->assertSame([], $this->due(LeaveEarnType::Anniversary, today: '2026-11-19', join: '2023-11-20'));   // not yet
        $this->assertSame([], $this->due(LeaveEarnType::Anniversary, today: '2026-11-20', join: '2026-01-01'));   // less than a year
    }

    // ---------------------------------------------------------------- helpers

    /**
     * @param  (callable(CarbonImmutable, CarbonImmutable): float)|null  $worked
     * @return list<EarnedPeriod>
     */
    private function due(
        LeaveEarnType $type,
        string $today,
        string $join = '2020-01-01',
        ?string $separation = null,
        ?string $earnFrom = null,
        int $startMonth = 1,
        float $perDays = 0,
        ?callable $worked = null,
    ): array {
        $leaveType = new LeaveType(['code' => 'EL', 'name' => 'Earned leave', 'earn_type' => $type, 'earn_per_days_worked' => $perDays ?: null]);

        return (new LeaveEarning)->due(
            $leaveType, 12.0, CarbonImmutable::parse($join), $separation ? CarbonImmutable::parse($separation) : null,
            CarbonImmutable::parse($today), $earnFrom ? CarbonImmutable::parse($earnFrom) : null, $startMonth, $worked,
        );
    }

    /**
     * @param  list<EarnedPeriod>  $periods
     * @return list<array{0: string, 1: string, 2: int, 3: float}> [start, credit date, year, days]
     */
    private function summary(array $periods): array
    {
        return array_map(fn (EarnedPeriod $p) => [$p->start->toDateString(), $p->creditOn->toDateString(), $p->year, $p->days], $periods);
    }
}
