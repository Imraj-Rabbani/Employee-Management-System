<?php

namespace App\Core\Support;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * A payroll period: year + month + cycle → first day, last day, number of days. [PR-01]
 * Monthly = whole month; first half = 1st–15th; second half = 16th–end of month.
 *
 *     $p = Period::of(2026, 2);                      Feb 2026
 *     $p->start()->toDateString()  "2026-02-01"      $p->days()  28
 *     Period::of(2026, 2, PeriodCycle::SecondHalf)   16–28 Feb
 *     $p->contains('2026-02-10')                     true
 */
final readonly class Period
{
    private function __construct(
        public int $year,
        public int $month,
        public PeriodCycle $cycle,
    ) {}

    public static function of(int $year, int $month, PeriodCycle $cycle = PeriodCycle::Monthly): self
    {
        if ($month < 1 || $month > 12) {
            throw new InvalidArgumentException("Month must be 1–12, got {$month}.");
        }

        return new self($year, $month, $cycle);
    }

    /**
     * The period a date falls in. Monthly → its month. For half-month payroll pass either half: the day picks
     * the right one (10th → 1st half, 20th → 2nd half), so the result always contains the date.
     */
    public static function containing(CarbonImmutable|string $date, PeriodCycle $cycle = PeriodCycle::Monthly): self
    {
        $date = CarbonImmutable::parse($date);

        if ($cycle !== PeriodCycle::Monthly) {
            $cycle = $date->day <= 15 ? PeriodCycle::FirstHalf : PeriodCycle::SecondHalf;
        }

        return new self($date->year, $date->month, $cycle);
    }

    public function start(): CarbonImmutable
    {
        $first = CarbonImmutable::create($this->year, $this->month, 1)->startOfDay();

        return $this->cycle === PeriodCycle::SecondHalf ? $first->setDay(16) : $first;
    }

    public function end(): CarbonImmutable
    {
        $first = CarbonImmutable::create($this->year, $this->month, 1)->startOfDay();

        return $this->cycle === PeriodCycle::FirstHalf ? $first->setDay(15) : $first->endOfMonth()->startOfDay();
    }

    /** Calendar days in the period. */
    public function days(): int
    {
        return (int) $this->start()->diffInDays($this->end()) + 1;
    }

    public function contains(CarbonImmutable|string $date): bool
    {
        $date = CarbonImmutable::parse($date)->startOfDay();

        return $date->betweenIncluded($this->start(), $this->end());
    }

    public function next(): self
    {
        return match ($this->cycle) {
            PeriodCycle::FirstHalf => new self($this->year, $this->month, PeriodCycle::SecondHalf),
            default => self::fromFirst($this->start()->addMonthNoOverflow(), $this->cycle === PeriodCycle::SecondHalf ? PeriodCycle::FirstHalf : PeriodCycle::Monthly),
        };
    }

    public function previous(): self
    {
        return match ($this->cycle) {
            PeriodCycle::SecondHalf => new self($this->year, $this->month, PeriodCycle::FirstHalf),
            default => self::fromFirst($this->start()->subMonthNoOverflow(), $this->cycle === PeriodCycle::FirstHalf ? PeriodCycle::SecondHalf : PeriodCycle::Monthly),
        };
    }

    public function equals(self $other): bool
    {
        return $this->year === $other->year && $this->month === $other->month && $this->cycle === $other->cycle;
    }

    /** "February 2026", "February 2026 (1st half)" */
    public function label(): string
    {
        $label = $this->start()->format('F Y');

        return $this->cycle === PeriodCycle::Monthly ? $label : "{$label} ({$this->cycle->label()})";
    }

    private static function fromFirst(CarbonImmutable $date, PeriodCycle $cycle): self
    {
        return new self($date->year, $date->month, $cycle);
    }
}
