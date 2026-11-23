<?php

namespace App\Modules\Payroll\Models;

use App\Core\Casts\MoneyCast;
use App\Core\Models\TenantModel;
use App\Modules\Payroll\Concerns\GuardedByRunStatus;
use App\Core\Support\Money;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\EmployeeBankAccount;
use App\Modules\Hrm\Models\Entity;
use App\Modules\Payroll\Data\SalaryBreakdown;
use App\Modules\Payroll\Data\SalaryLine;
use App\Modules\Payroll\Enums\PayrollCalcStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One employee in a payroll run: the snapshot of how they were paid (days, totals, bank, hold flags, warnings).
 * The per-head amounts are its PayrollItems. Written by the pipeline's Persist stage (10.3); logs nothing itself
 * (the run is recalculated many times while in draft).
 *
 * @property int $id
 * @property int $payroll_run_id
 * @property int $employee_id
 * @property int|null $entity_id the employee's entity when the run was calculated
 * @property int|null $bank_account_id
 * @property string $period_days
 * @property string $payable_days
 * @property string $lwp_days
 * @property string $absent_days
 * @property string $overtime_hours approved overtime hours paid (10.6)
 * @property bool $joiner_deferred joined after the threshold day: nothing paid here, the next run pays it as arrears
 * @property Money $basic
 * @property Money $gross
 * @property Money $deductions
 * @property Money $net
 * @property bool $is_on_hold
 * @property bool $hold_bank_transfer
 * @property bool $is_separated
 * @property PayrollCalcStatus $calc_status
 * @property list<string>|null $warnings
 * @property string|null $error
 * @property Carbon|null $calculated_at
 * @property-read PayrollRun $run
 * @property-read Employee $employee
 */
class PayrollRunEmployee extends TenantModel
{
    use GuardedByRunStatus;

    /** @var list<string> */
    protected static $recordEvents = [];

    protected $fillable = [
        'payroll_run_id', 'employee_id', 'entity_id', 'bank_account_id',
        'period_days', 'payable_days', 'lwp_days', 'absent_days', 'overtime_hours', 'basic', 'gross', 'deductions', 'net',
        'is_on_hold', 'hold_bank_transfer', 'is_separated', 'joiner_deferred', 'calc_status', 'warnings', 'error', 'calculated_at',
    ];

    protected $attributes = ['calc_status' => 'pending'];

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return [
            'period_days' => 'decimal:2',
            'payable_days' => 'decimal:2',
            'lwp_days' => 'decimal:2',
            'absent_days' => 'decimal:2',
            'overtime_hours' => 'decimal:2',
            'basic' => MoneyCast::class,
            'gross' => MoneyCast::class,
            'deductions' => MoneyCast::class,
            'net' => MoneyCast::class,
            'is_on_hold' => 'boolean',
            'hold_bank_transfer' => 'boolean',
            'is_separated' => 'boolean',
            'joiner_deferred' => 'boolean',
            'calc_status' => PayrollCalcStatus::class,
            'warnings' => 'array',
            'calculated_at' => 'datetime',
        ];
    }

    /** The items as a breakdown (lines in head order, gross / deductions / net), like a salary. Needs items.head. */
    public function breakdown(): SalaryBreakdown
    {
        return SalaryBreakdown::fromLines(
            $this->items->map(fn (PayrollItem $item) => new SalaryLine($item->head, $item->source->label(), $item->amount))->all()
        );
    }

    public function hasWarnings(): bool
    {
        return ! empty($this->warnings);
    }

    /** @return BelongsTo<PayrollRun, $this> */
    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return BelongsTo<Entity, $this> */
    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    /** @return BelongsTo<EmployeeBankAccount, $this> */
    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(EmployeeBankAccount::class, 'bank_account_id');
    }

    /** @return HasMany<PayrollItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(PayrollItem::class);
    }
}
