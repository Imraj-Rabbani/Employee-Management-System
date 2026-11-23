<?php

namespace App\Modules\Payroll\Models;

use App\Core\Casts\MoneyCast;
use App\Core\Models\TenantModel;
use App\Core\Support\Money;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Payroll\Concerns\GuardedByRunStatus;
use App\Modules\Payroll\Enums\PayrollItemSource;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One salary head of one employee in one run (legacy `payroll`): what was paid (amount), what the full period would
 * have been (actual), how much of it is back pay (arrear) and which stage made it (source).
 * Earning or deduction: the head's type says; amounts are stored positive. Logs nothing (rewritten on every re-run).
 *
 *     PayrollItem::where('employee_id', $id)->where('salary_head_id', $tax->id)->sum('amount')   year to date
 *
 * @property int $id
 * @property int $payroll_run_id
 * @property int $payroll_run_employee_id
 * @property int $employee_id
 * @property int $salary_head_id
 * @property Money $amount
 * @property Money $actual
 * @property Money $arrear
 * @property PayrollItemSource $source
 * @property-read SalaryHead $head
 */
class PayrollItem extends TenantModel
{
    use GuardedByRunStatus;

    /** @var list<string> */
    protected static $recordEvents = [];

    protected $fillable = ['payroll_run_id', 'payroll_run_employee_id', 'employee_id', 'salary_head_id', 'amount', 'actual', 'arrear', 'source'];

    protected $attributes = ['amount' => '0.00', 'actual' => '0.00', 'arrear' => '0.00', 'source' => 'structure'];

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return [
            'amount' => MoneyCast::class,
            'actual' => MoneyCast::class,
            'arrear' => MoneyCast::class,
            'source' => PayrollItemSource::class,
        ];
    }

    /** @return BelongsTo<PayrollRun, $this> */
    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    /** @return BelongsTo<PayrollRunEmployee, $this> */
    public function runEmployee(): BelongsTo
    {
        return $this->belongsTo(PayrollRunEmployee::class, 'payroll_run_employee_id');
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
}
