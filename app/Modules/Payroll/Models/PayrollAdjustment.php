<?php

namespace App\Modules\Payroll\Models;

use App\Core\Casts\MoneyCast;
use App\Core\Models\TenantModel;
use App\Core\Support\Money;
use App\Core\Support\Period;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Payroll\Database\Factories\PayrollAdjustmentFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A one-time earning or deduction for one employee in one month (legacy `adjustments`), e.g. "Shift allowance
 * 2,000" or "Canteen 500". The head's type says earning or deduction; the amount is always positive.
 * The run of that month takes the approved, still free ones (Adjustments stage, 10.6) and sets payroll_run_id.
 * CRUD, Excel import and approval come in 10.6.
 *
 * @property int $id
 * @property int $employee_id
 * @property int $salary_head_id
 * @property int $year
 * @property int $month
 * @property Money $amount
 * @property string $reason
 * @property int|null $payroll_run_id the run that paid it; NULL = not paid yet
 * @property int|null $requested_by_user_id
 * @property Carbon|null $approved_at
 * @property-read SalaryHead $head
 */
#[UseFactory(PayrollAdjustmentFactory::class)]
class PayrollAdjustment extends TenantModel
{
    /** @use HasFactory<PayrollAdjustmentFactory> */
    use HasFactory;

    /** payroll_run_id and approved_at are NOT fillable: only the run / the approval set them. */
    protected $fillable = ['employee_id', 'salary_head_id', 'year', 'month', 'amount', 'reason', 'requested_by_user_id'];

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return ['year' => 'integer', 'month' => 'integer', 'amount' => MoneyCast::class, 'approved_at' => 'datetime'];
    }

    /**
     * Approved and not yet paid adjustments of a month: what a run of that month will take.
     *
     * @param  Builder<self>  $query
     */
    public function scopeDueIn(Builder $query, Period $period): void
    {
        $query->where('year', $period->year)->where('month', $period->month)
            ->whereNotNull('approved_at')->whereNull('payroll_run_id');
    }

    public function isPaid(): bool
    {
        return $this->payroll_run_id !== null;
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return BelongsTo<SalaryHead, $this> */
    public function head(): BelongsTo
    {
        return $this->belongsTo(SalaryHead::class, 'salary_head_id');
    }

    /** @return BelongsTo<PayrollRun, $this> */
    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }
}
