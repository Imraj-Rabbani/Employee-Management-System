<?php

namespace App\Modules\Payroll\Stages;

use App\Modules\Payroll\Data\PayrollContext;
use Closure;

/**
 * Stage 3. The days the employee is paid for: every day from workFrom to workTo (joiners and leavers get only the
 * days they work). Leave without pay and absent days are taken off here in 10.5; until then they are 0.
 */
class ComputePayableDays
{
    public function handle(PayrollContext $ctx, Closure $next): mixed
    {
        $ctx->lwpDays = 0.0;
        $ctx->absentDays = 0.0;
        $ctx->payableDays = (float) ((int) $ctx->workFrom->diffInDays($ctx->workTo) + 1) - $ctx->lwpDays - $ctx->absentDays;

        return $next($ctx);
    }
}
