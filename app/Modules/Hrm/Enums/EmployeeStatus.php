<?php

namespace App\Modules\Hrm\Enums;

use App\Core\Enums\HasOptions;

/**
 * Where an employee stands (employees.status, Phase 5). Not in legacy's Constants: legacy only has
 * employees.status 1 = active / 0 = not. Migration (Phase 15): 1 → Active, 0 → Separated.
 *
 *     EmployeeStatus::Active->color()    'success'   for <x-ui.badge>
 *     $employee->status->isEmployed()    true for Active and Suspended (still employed)
 */
enum EmployeeStatus: int
{
    use HasOptions;

    case Active = 1;
    case Suspended = 2;     // still employed, but not working / not paid normally (disciplinary)
    case Separated = 3;     // left: resigned, retired, terminated ... (details in the separation, Phase 13)

    public function label(): string
    {
        return match ($this) {
            self::Active => __('Active'),
            self::Suspended => __('Suspended'),
            self::Separated => __('Separated'),
        };
    }

    /** Colour for <x-ui.badge>. */
    public function color(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Suspended => 'warning',
            self::Separated => 'neutral',
        };
    }

    /** Still on the payroll / headcount (Active or Suspended). */
    public function isEmployed(): bool
    {
        return $this !== self::Separated;
    }
}
