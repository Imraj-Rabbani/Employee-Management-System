<?php

namespace App\Modules\Hrm\Data;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * One leave year. It starts on the 1st of the company's start month (setting leave.year_start_month,
 * default 1 = January, like legacy `leaveStartMonth`) and is named after the calendar year it STARTS in:
 * with start month 7, LeaveYear 2026 = 1 July 2026 – 30 June 2027. [LV-03]
 *
 *     LeaveYear::containing('2027-03-10')    the leave year that date is in
 *     LeaveYear::of(2026)                    by its number
 *     $year->start(), $year->end(), $year->next(), $year->label()
 *
 * Pass $startMonth to skip reading the setting (unit tests).
 */
final readonly class LeaveYear
{
    public const SETTING = 'leave.year_start_month';

    private function __construct(public int $year, public int $startMonth) {}

    public static function of(int $year, ?int $startMonth = null): self
    {
        return new self($year, $startMonth ?? self::startMonth());
    }

    public static function containing(CarbonInterface|string $date, ?int $startMonth = null): self
    {
        $date = CarbonImmutable::parse($date);
        $startMonth ??= self::startMonth();

        return new self($date->month >= $startMonth ? $date->year : $date->year - 1, $startMonth);
    }

    /** The leave year of today, in the company's timezone. */
    public static function current(?int $startMonth = null): self
    {
        return self::containing(CarbonImmutable::now(settings('timezone', config('app.timezone'))), $startMonth);
    }

    public static function startMonth(): int
    {
        $month = (int) settings(self::SETTING, 1);

        return $month >= 1 && $month <= 12 ? $month : 1;
    }

    public function start(): CarbonImmutable
    {
        return CarbonImmutable::create($this->year, $this->startMonth, 1)->startOfDay();
    }

    public function end(): CarbonImmutable
    {
        return $this->start()->addYear()->subDay();
    }

    public function contains(CarbonInterface|string $date): bool
    {
        return CarbonImmutable::parse($date)->between($this->start(), $this->end());
    }

    public function next(): self
    {
        return new self($this->year + 1, $this->startMonth);
    }

    public function previous(): self
    {
        return new self($this->year - 1, $this->startMonth);
    }

    /** "2026" for a calendar leave year, "Jul 2026 – Jun 2027" otherwise. */
    public function label(): string
    {
        return $this->startMonth === 1
            ? (string) $this->year
            : $this->start()->translatedFormat('M Y').' – '.$this->end()->translatedFormat('M Y');
    }
}
