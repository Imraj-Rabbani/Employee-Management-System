<?php

namespace App\Modules\Payroll\Stages;

use App\Core\Support\PeriodCycle;
use App\Modules\Payroll\Data\PayrollContext;
use App\Modules\Payroll\Exceptions\PayrollException;
use Carbon\CarbonImmutable;
use Closure;

/**
 * Stage 1. The run's period, and the days of it this employee works: from the later of the period's first day and
 * the joining date, to the earlier of its last day and the separation date.
 * A half-month cycle pays half of the monthly salary (cycleShare 50).
 */
class ResolvePeriod
{
    public function handle(PayrollContext $ctx, Closure $next): mixed
    {
        $period = $ctx->run->period();
        $ctx->period = $period;
        $ctx->periodDays = $period->days();
        $ctx->cycleShare = $period->cycle === PeriodCycle::Monthly ? '100' : '50';

        $join = CarbonImmutable::parse($ctx->employee->join_date->toDateString());
        $left = $ctx->employee->separation_date ? CarbonImmutable::parse($ctx->employee->separation_date->toDateString()) : null;

        $ctx->workFrom = $join->gt($period->start()) ? $join : $period->start();
        $ctx->workTo = $left !== null && $left->lt($period->end()) ? $left : $period->end();

        if ($ctx->workFrom->gt($ctx->workTo)) {
            throw new PayrollException(__('Not employed in :period (joined :join, left :left).', [
                'period' => $period->label(), 'join' => format_date($join), 'left' => $left ? format_date($left) : '-',
            ]));
        }

        return $next($ctx);
    }
}
