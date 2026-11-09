<?php

namespace App\Modules\Hrm\Enums;

use App\Core\Enums\HasOptions;

/**
 * Why a manager asks for people (manpower_requests.type, 6.2). [REC-01]
 *
 *     New           a new seat: the team grows
 *     Replacement   someone left (or is leaving): manpower_requests.replaces_employee_id says who
 */
enum ManpowerRequestType: string
{
    use HasOptions;

    case New = 'new';
    case Replacement = 'replacement';

    public function label(): string
    {
        return match ($this) {
            self::New => __('New position'),
            self::Replacement => __('Replacement'),
        };
    }
}
