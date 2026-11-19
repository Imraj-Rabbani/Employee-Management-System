<?php

namespace App\Modules\Payroll\Enums;

use App\Core\Enums\HasOptions;

/** What a payroll run pays (payroll_runs.run_type). Only ONE regular run per period and scope. */
enum PayrollRunType: string
{
    use HasOptions;

    case Regular = 'regular';                    // the monthly salary
    case HeadWise = 'head_wise';                 // one head only, e.g. festival bonus (needs salary_head_id)
    case OffCycle = 'off_cycle';                 // extra payment outside the normal run, e.g. uploaded amounts
    case FinalSettlement = 'final_settlement';   // a leaver's last pay (Phase 13)

    public function label(): string
    {
        return match ($this) {
            self::Regular => __('Regular'),
            self::HeadWise => __('Head-wise'),
            self::OffCycle => __('Off-cycle'),
            self::FinalSettlement => __('Final settlement'),
        };
    }

    /** A head-wise run pays a single head, so the run must name it. */
    public function needsHead(): bool
    {
        return $this === self::HeadWise;
    }
}
