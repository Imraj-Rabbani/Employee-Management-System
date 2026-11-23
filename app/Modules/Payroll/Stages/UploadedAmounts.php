<?php

namespace App\Modules\Payroll\Stages;

use App\Core\Support\Money;
use App\Modules\Payroll\Data\PayrollContext;
use App\Modules\Payroll\Data\PayrollLine;
use App\Modules\Payroll\Enums\PayrollItemSource;
use App\Modules\Payroll\Models\PayrollRunAmount;
use Closure;

/**
 * The one stage of an upload run (off-cycle, or head-wise with uploaded amounts): each uploaded amount of the
 * employee becomes a line (source Upload), paid as it is: no proration, no other heads.
 */
class UploadedAmounts
{
    public function handle(PayrollContext $ctx, Closure $next): mixed
    {
        $ctx->payableDays = (float) ((int) $ctx->workFrom->diffInDays($ctx->workTo) + 1);

        $amounts = PayrollRunAmount::query()->where('payroll_run_id', $ctx->run->id)->where('employee_id', $ctx->employee->id)
            ->with('head')->orderBy('id')->get();
        foreach ($amounts as $row) {
            $ctx->lines[] = new PayrollLine($row->head, PayrollItemSource::Upload, $row->amount, $row->amount, Money::zero());
        }
        if ($amounts->isEmpty()) {
            $ctx->warn(__('No uploaded amount.'));
        }

        return $next($ctx);
    }
}
