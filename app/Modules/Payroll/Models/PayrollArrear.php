<?php

namespace App\Modules\Payroll\Models;

use App\Core\Casts\MoneyCast;
use App\Core\Models\TenantModel;
use App\Core\Support\Money;
use App\Modules\Payroll\Enums\ArrearReason;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Back pay for one head of an EARLIER period, paid by a later run (10.7). Written only by the Persist stage
 * (replaced on every calculation, like payroll_items); no own history. The paying run's item of that head
 * includes the amount in its `arrear` column.
 *
 * @property int $id
 * @property int $payroll_run_id
 * @property int $payroll_run_employee_id
 * @property int $employee_id
 * @property int $salary_head_id
 * @property int $for_payroll_run_id
 * @property int $for_payroll_run_employee_id
 * @property Carbon $for_period_start
 * @property Carbon $for_period_end
 * @property ArrearReason $reason
 * @property Money $due
 * @property Money $paid_before
 * @property Money $amount
 * @property-read SalaryHead $head
 * @property-read PayrollRun $forRun
 */
class PayrollArrear extends TenantModel
{
    /** @var list<string> no activity log entries */
    protected static $recordEvents = [];

    protected $guarded = ['id'];

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return [
            'for_period_start' => 'date',
            'for_period_end' => 'date',
            'reason' => ArrearReason::class,
            'due' => MoneyCast::class,
            'paid_before' => MoneyCast::class,
            'amount' => MoneyCast::class,
        ];
    }

    /** @return BelongsTo<SalaryHead, $this> */
    public function head(): BelongsTo
    {
        return $this->belongsTo(SalaryHead::class, 'salary_head_id');
    }

    /** @return BelongsTo<PayrollRun, $this> */
    public function forRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'for_payroll_run_id');
    }
}
