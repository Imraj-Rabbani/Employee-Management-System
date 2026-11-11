<?php

namespace App\Modules\Payroll\Enums;

use App\Core\Enums\HasOptions;

/**
 * The result of one employee's day (attendance_days.status). [TA-05]
 * Late / early exit are not statuses: a late day is Present with late_minutes > 0 (and is_late).
 */
enum AttendanceStatus: int
{
    use HasOptions;

    case Present = 1;
    case HalfDay = 2;   // came, but too late or too short (the shift's half-day rules)
    case Absent = 3;    // a working day without punches and without leave
    case Leave = 4;     // approved leave (Phase 8)
    case Off = 5;       // weekend, or a day off in the roster
    case Holiday = 6;   // public / festival holiday in the employee's calendar

    public function label(): string
    {
        return match ($this) {
            self::Present => __('Present'),
            self::HalfDay => __('Half day'),
            self::Absent => __('Absent'),
            self::Leave => __('Leave'),
            self::Off => __('Off'),
            self::Holiday => __('Holiday'),
        };
    }

    /** Colour for <x-ui.badge> and the calendar (7.8). */
    public function color(): string
    {
        return match ($this) {
            self::Present => 'success',
            self::HalfDay => 'warning',
            self::Absent => 'danger',
            self::Leave => 'info',
            self::Off, self::Holiday => 'neutral',
        };
    }
}
