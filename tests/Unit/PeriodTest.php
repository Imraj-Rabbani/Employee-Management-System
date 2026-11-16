<?php

namespace Tests\Unit;

use App\Core\Support\Period;
use App\Core\Support\PeriodCycle;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** Year + month + cycle → start, end, days. */
class PeriodTest extends TestCase
{
    public function test_a_monthly_period(): void
    {
        $p = Period::of(2026, 2);

        $this->assertSame('2026-02-01', $p->start()->toDateString());
        $this->assertSame('2026-02-28', $p->end()->toDateString());
        $this->assertSame(28, $p->days());
        $this->assertSame('February 2026', $p->label());
    }

    public function test_leap_year_february(): void
    {
        $this->assertSame(29, Period::of(2028, 2)->days());
    }

    public function test_half_periods(): void
    {
        $first = Period::of(2026, 3, PeriodCycle::FirstHalf);
        $second = Period::of(2026, 3, PeriodCycle::SecondHalf);

        $this->assertSame(['2026-03-01', '2026-03-15', 15], [$first->start()->toDateString(), $first->end()->toDateString(), $first->days()]);
        $this->assertSame(['2026-03-16', '2026-03-31', 16], [$second->start()->toDateString(), $second->end()->toDateString(), $second->days()]);
        $this->assertSame('March 2026 (1st half)', $first->label());
    }

    public function test_contains(): void
    {
        $p = Period::of(2026, 3, PeriodCycle::SecondHalf);

        $this->assertTrue($p->contains('2026-03-16'));
        $this->assertTrue($p->contains('2026-03-31'));
        $this->assertFalse($p->contains('2026-03-15'));
    }

    public function test_next_and_previous(): void
    {
        $this->assertTrue(Period::of(2026, 12)->next()->equals(Period::of(2027, 1)));
        $this->assertTrue(Period::of(2026, 1)->previous()->equals(Period::of(2025, 12)));
        $this->assertTrue(Period::of(2026, 3, PeriodCycle::FirstHalf)->next()->equals(Period::of(2026, 3, PeriodCycle::SecondHalf)));
        $this->assertTrue(Period::of(2026, 3, PeriodCycle::SecondHalf)->next()->equals(Period::of(2026, 4, PeriodCycle::FirstHalf)));
        $this->assertTrue(Period::of(2026, 3, PeriodCycle::FirstHalf)->previous()->equals(Period::of(2026, 2, PeriodCycle::SecondHalf)));
    }

    public function test_containing_a_date(): void
    {
        $this->assertTrue(Period::containing('2026-07-19')->equals(Period::of(2026, 7)));
        // Half-month payroll: the day picks the half, whichever half was passed.
        $this->assertTrue(Period::containing('2026-07-19', PeriodCycle::FirstHalf)->equals(Period::of(2026, 7, PeriodCycle::SecondHalf)));
        $this->assertTrue(Period::containing('2026-07-15', PeriodCycle::SecondHalf)->equals(Period::of(2026, 7, PeriodCycle::FirstHalf)));
    }

    public function test_invalid_month(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Period::of(2026, 13);
    }
}
