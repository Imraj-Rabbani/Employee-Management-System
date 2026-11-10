<?php

namespace App\Modules\Payroll\Data;

use App\Modules\Payroll\Models\RosterItem;
use App\Modules\Payroll\Models\Shift;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The rules of the shift worked on one day, as plain values (no database): what AttendanceProcessor needs.
 * Built from a Shift, with a roster cell's own start / end times for that day when it has them.
 */
final readonly class ShiftTimes
{
    public function __construct(
        public string $start,                 // "09:00"
        public string $end,                   // "18:00"; at or before the start = the next day
        public int $graceMinutes = 0,
        public ?int $graceLimitPerMonth = null,
        public int $flexibilityMinutes = 0,
        public ?int $halfDayMinutes = null,   // worked less than this → half day
        public ?string $halfDayAfter = null,  // came in after this time → half day
        public ?int $shiftId = null,
    ) {}

    public static function fromShift(Shift $shift, ?RosterItem $cell = null): self
    {
        $hhmm = fn (?string $t) => $t === null ? null : substr($t, 0, 5);

        return new self(
            start: $hhmm($cell->start_time ?? null) ?? $hhmm($shift->start_time),
            end: $hhmm($cell->end_time ?? null) ?? $hhmm($shift->end_time),
            graceMinutes: $shift->grace_minutes,
            graceLimitPerMonth: $shift->grace_limit_per_month,
            flexibilityMinutes: $shift->flexibility_minutes,
            halfDayMinutes: $shift->half_day_hours === null ? null : (int) round((float) $shift->half_day_hours * 60),
            halfDayAfter: $hhmm($shift->half_day_after),
            shiftId: $shift->id,
        );
    }

    /** When the shift starts on working day $date. */
    public function startOn(CarbonInterface $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date->toDateString().' '.$this->start);
    }

    /** When it ends: the next calendar day for an overnight shift (end at or before start). */
    public function endOn(CarbonInterface $date): CarbonImmutable
    {
        $end = CarbonImmutable::parse($date->toDateString().' '.$this->end);

        return $this->end <= $this->start ? $end->addDay() : $end;
    }

    /** The "half day if in after" moment; for an overnight shift a time before the start means the next day. */
    public function halfDayAfterOn(CarbonInterface $date): ?CarbonImmutable
    {
        if ($this->halfDayAfter === null) {
            return null;
        }
        $at = CarbonImmutable::parse($date->toDateString().' '.$this->halfDayAfter);

        return $this->halfDayAfter < $this->start ? $at->addDay() : $at;
    }
}
