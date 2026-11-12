<?php

namespace App\Modules\Hrm\Data;

use App\Modules\Hrm\Enums\Gender;
use App\Modules\Hrm\Enums\Religion;
use Carbon\CarbonImmutable;

/**
 * Everything LeaveRules needs to judge ONE leave request, as plain values (no database). [LV-01]
 * Built from the database by LeaveRequestInputs; built by hand in unit tests.
 *
 * $daysOff must cover the request PLUS the days around it (the sandwich rule looks at the days off right
 * before and after the leave): LeaveRequestInputs takes ±31 days.
 */
final readonly class LeaveRequest
{
    /**
     * @param  array<string, true>  $daysOff  'Y-m-d' => true for weekends / holidays / roster days off
     */
    public function __construct(
        public CarbonImmutable $from,
        public CarbonImmutable $to,
        public CarbonImmutable $today,          // in the company timezone
        public bool $halfDay = false,
        public array $daysOff = [],
        // The employee.
        public ?Gender $gender = null,
        public ?Religion $religion = null,
        public ?CarbonImmutable $joinDate = null,
        public ?CarbonImmutable $confirmationDate = null,
        public ?CarbonImmutable $separationDate = null,
        // Their leave of this type.
        public ?float $entitlement = null,      // days per year in their profile; NULL = type not in the profile
        public float $balance = 0.0,            // in the leave year of $from
        public int $timesTaken = 0,             // earlier applications of this type in the whole service (not rejected / withdrawn)
        public bool $overlaps = false,          // another leave of theirs covers one of these days
        // The application.
        public bool $hasReason = false,
        public bool $hasAttachment = false,
        public bool $byHr = false,              // HR enters it: HR-only types allowed, no notice / post-facto limits
    ) {}

    /** The same request, with the overlap answer (ApplyForLeave knows it only after the dates were counted). */
    public function withOverlaps(bool $overlaps): self
    {
        return new self(...[...get_object_vars($this), 'overlaps' => $overlaps]);
    }
}
