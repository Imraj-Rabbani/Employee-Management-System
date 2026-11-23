<?php

namespace App\Modules\Payroll\Models;

use App\Core\Casts\MoneyCast;
use App\Core\Models\TenantModel;
use App\Core\Support\Money;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Payroll\Concerns\GuardedByRunStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One uploaded amount of an upload run (head-wise with "Uploaded amounts", or off-cycle): employee, head, amount.
 * Written only by Imports\RunAmountImporter (an upload replaces all of the run's amounts); the calculation turns them
 * into items (Stages\UploadedAmounts). Changes only while the run is open (GuardedByRunStatus); no own history.
 *
 * @property int $id
 * @property int $payroll_run_id
 * @property int $employee_id
 * @property int $salary_head_id
 * @property Money $amount
 * @property string|null $note
 * @property-read SalaryHead $head
 */
class PayrollRunAmount extends TenantModel
{
    use GuardedByRunStatus;

    /** @var list<string> */
    protected static $recordEvents = [];

    protected $fillable = ['payroll_run_id', 'employee_id', 'salary_head_id', 'amount', 'note'];

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return ['amount' => MoneyCast::class];
    }

    /** @return BelongsTo<SalaryHead, $this> */
    public function head(): BelongsTo
    {
        return $this->belongsTo(SalaryHead::class, 'salary_head_id');
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
