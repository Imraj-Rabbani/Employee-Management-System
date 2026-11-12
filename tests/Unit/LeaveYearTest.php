<?php

namespace Tests\Unit;

use App\Modules\Hrm\Data\LeaveYear;
use PHPUnit\Framework\TestCase;

/**
 * Task 8.2: which leave year a date is in. The start month is passed in, so no database or settings needed. [LV-03]
 */
class LeaveYearTest extends TestCase
{
    public function test_a_calendar_leave_year(): void
    {
        $year = LeaveYear::containing('2026-11-12', startMonth: 1);

        $this->assertSame(2026, $year->year);
        $this->assertSame('2026-01-01', $year->start()->toDateString());
        $this->assertSame('2026-12-31', $year->end()->toDateString());
        $this->assertSame('2026', $year->label());
        $this->assertSame(2027, $year->next()->year);
    }

    public function test_a_leave_year_from_july_is_named_after_the_year_it_starts_in(): void
    {
        $this->assertSame(2026, LeaveYear::containing('2026-07-01', startMonth: 7)->year);
        $this->assertSame(2026, LeaveYear::containing('2027-06-30', startMonth: 7)->year);
        $this->assertSame(2025, LeaveYear::containing('2026-06-30', startMonth: 7)->year);

        $year = LeaveYear::of(2026, startMonth: 7);
        $this->assertSame('2026-07-01', $year->start()->toDateString());
        $this->assertSame('2027-06-30', $year->end()->toDateString());
        $this->assertTrue($year->contains('2027-02-28'));
        $this->assertFalse($year->contains('2027-07-01'));
        $this->assertSame('2027-07-01', $year->next()->start()->toDateString());
        $this->assertSame('Jul 2026 – Jun 2027', $year->label());
    }

    public function test_a_leave_year_from_march_ends_on_the_last_day_of_february(): void
    {
        $this->assertSame('2028-02-29', LeaveYear::of(2027, startMonth: 3)->end()->toDateString());   // leap year
    }
}
