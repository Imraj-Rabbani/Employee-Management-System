<?php

namespace App\Modules\Payroll\Enums;

use App\Core\Enums\HasOptions;

/**
 * Where a punch came from (punch_logs.source). Same numbers as legacy `punchlog.type`,
 * so migrated punches keep their meaning. Here 0 IS a real value (manual entry), not "none".
 */
enum PunchSource: int
{
    use HasOptions;

    case Manual = 0;   // typed in by HR (e.g. from an approved correction, 7.6)
    case Csv = 1;      // uploaded file (7.3c)
    case Device = 2;   // ZKTeco access-control device (7.3d)
    case Web = 3;      // Self Service, browser (7.3e)
    case Mobile = 4;   // Self Service, mobile app

    public function label(): string
    {
        return match ($this) {
            self::Manual => __('Manual entry'),
            self::Csv => __('CSV upload'),
            self::Device => __('Device'),
            self::Web => __('Web punch'),
            self::Mobile => __('Mobile punch'),
        };
    }
}
