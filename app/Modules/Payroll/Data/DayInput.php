<?php

namespace App\Modules\Payroll\Data;

use App\Modules\Payroll\Enums\DayType;
use Carbon\CarbonImmutable;

/**
 * Everything AttendanceProcessor needs for ONE employee's working day, already worked out (no database):
 * the kind of day, the shift (if any), leave, the punches of the day's window, and how often grace was
 * used earlier this month. Built by AttendanceInputs (7.4c); built by hand in the unit tests.
 */
final readonly class DayInput
{
    /**
     * @param  list<CarbonImmutable>  $punches  in time order, only those that belong to this working day
     */
    public function __construct(
        public CarbonImmutable $date,
        public DayType $dayType,
        public ?ShiftTimes $shift,
        public array $punches = [],
        public bool $onLeave = false,
        public int $graceUsedThisMonth = 0,
    ) {}

    /** The same day with another grace count (set while processing days in order). */
    public function withGraceUsed(int $count): self
    {
        return new self($this->date, $this->dayType, $this->shift, $this->punches, $this->onLeave, $count);
    }
}
