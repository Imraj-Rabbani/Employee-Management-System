<?php

namespace App\Modules\Payroll\Models;

use App\Core\Casts\MoneyCast;
use App\Core\Models\TenantModel;
use App\Core\Support\Money;
use App\Core\Support\Period;
use App\Core\Support\PeriodCycle;
use App\Models\User;
use App\Modules\Hrm\Models\Entity;
use App\Modules\Hrm\Services\EmployeeVisibility;
use App\Modules\Payroll\Database\Factories\PayrollRunFactory;
use App\Modules\Payroll\Enums\PayrollRunStatus;
use App\Modules\Payroll\Enums\PayrollRunType;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One payroll run: a period, a type and a scope (legacy `payroll.batch`). Its employees are PayrollRunEmployee rows,
 * their amounts PayrollItem rows. Made by CreatePayrollRun (10.2), calculated by the pipeline (10.3).
 *
 *     $run->period()->label()           "December 2026"
 *     $run->status->canMoveTo(PayrollRunStatus::Pending)
 *     PayrollRun::forPeriod($run->period()->previous())->regular()->first()   last month's run (variance, 10.8)
 *
 * @property int $id
 * @property string $code
 * @property int $year
 * @property int $month
 * @property PeriodCycle $cycle
 * @property Carbon $period_start
 * @property Carbon $period_end
 * @property Carbon|null $pay_date
 * @property PayrollRunType $run_type
 * @property int|null $salary_head_id head-wise runs only
 * @property int|null $entity_id NULL = whole company
 * @property PayrollRunStatus $status
 * @property string|null $batch_id
 * @property Money $total_gross
 * @property Money $total_deductions
 * @property Money $total_net
 * @property int $employee_count
 * @property string|null $note
 * @property int|null $created_by_user_id
 * @property Carbon|null $processed_at
 * @property Carbon|null $locked_at
 * @property int|null $locked_by_user_id
 * @property-read Entity|null $entity
 * @property-read SalaryHead|null $head
 */
#[UseFactory(PayrollRunFactory::class)]
class PayrollRun extends TenantModel
{
    /** @use HasFactory<PayrollRunFactory> */
    use HasFactory;

    /** status, totals and the lock stamps are NOT fillable: only the run's own steps may set them. */
    protected $fillable = ['code', 'year', 'month', 'cycle', 'period_start', 'period_end', 'pay_date', 'run_type', 'salary_head_id', 'entity_id', 'note', 'created_by_user_id'];

    protected $attributes = ['status' => 'draft', 'cycle' => 'monthly', 'run_type' => 'regular'];

    /** Totals are recalculated on every run: logging them would flood the history. */
    protected array $auditExcept = ['batch_id', 'total_gross', 'total_deductions', 'total_net', 'employee_count', 'processed_at'];

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'cycle' => PeriodCycle::class,
            'period_start' => 'date',
            'period_end' => 'date',
            'pay_date' => 'date',
            'run_type' => PayrollRunType::class,
            'status' => PayrollRunStatus::class,
            'total_gross' => MoneyCast::class,
            'total_deductions' => MoneyCast::class,
            'total_net' => MoneyCast::class,
            'employee_count' => 'integer',
            'processed_at' => 'datetime',
            'locked_at' => 'datetime',
        ];
    }

    /** The run's period as a value object (start, end, days, previous / next). */
    public function period(): Period
    {
        return Period::of($this->year, $this->month, $this->cycle);
    }

    /** Fills year, month, cycle, period_start and period_end from a Period. */
    public function setPeriod(Period $period): static
    {
        $this->year = $period->year;
        $this->month = $period->month;
        $this->cycle = $period->cycle;
        $this->period_start = $period->start();
        $this->period_end = $period->end();

        return $this;
    }

    /** Moves to the next status, or throws if the lifecycle does not allow it (e.g. locked → draft). */
    public function moveTo(PayrollRunStatus $to): void
    {
        if (! $this->status->canMoveTo($to)) {
            throw new \LogicException("Payroll run {$this->code} cannot go from {$this->status->value} to {$to->value}.");
        }
        $this->status = $to;
        $this->save();
    }

    /** Adds up the employees' rows into the run's totals (after a calculation). */
    public function refreshTotals(): void
    {
        $sums = $this->employees()
            ->selectRaw('COUNT(*) AS n, COALESCE(SUM(gross), 0) AS gross, COALESCE(SUM(deductions), 0) AS deductions, COALESCE(SUM(net), 0) AS net')
            ->toBase()->first();

        $this->employee_count = (int) $sums->n;
        $this->total_gross = Money::of((string) $sums->gross);
        $this->total_deductions = Money::of((string) $sums->deductions);
        $this->total_net = Money::of((string) $sums->net);
        $this->save();
    }

    /**
     * Runs of a period (all types).
     *
     * @param  Builder<self>  $query
     */
    public function scopeForPeriod(Builder $query, Period $period): void
    {
        $query->where('year', $period->year)->where('month', $period->month)->where('cycle', $period->cycle->value);
    }

    /** @param  Builder<self>  $query */
    public function scopeRegular(Builder $query): void
    {
        $query->where('run_type', PayrollRunType::Regular->value);
    }

    /**
     * Only runs $user may see: unrestricted users see all; a user limited to an entity sees that entity's runs
     * (not whole-company runs, which hold other entities' salaries). Same rule as rosters (EmployeeVisibility).
     *
     * @param  Builder<self>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $entityIds = app(EmployeeVisibility::class)->entityIdsFor($user);
        if ($entityIds !== null) {
            $query->whereIn('entity_id', $entityIds);
        }
    }

    /** "December 2026 · Regular · Factory" for lists and toasts. */
    public function title(): string
    {
        $parts = [$this->period()->label(), $this->run_type->label()];
        if ($this->run_type->needsHead() && $this->head) {
            $parts[] = $this->head->name;
        }
        $parts[] = $this->entity?->name ?? __('Whole company');

        return implode(' · ', $parts);
    }

    /** @return HasMany<PayrollRunEmployee, $this> */
    public function employees(): HasMany
    {
        return $this->hasMany(PayrollRunEmployee::class);
    }

    /** @return HasMany<PayrollItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(PayrollItem::class);
    }

    /** Adjustments this run has taken. @return HasMany<PayrollAdjustment, $this> */
    public function adjustments(): HasMany
    {
        return $this->hasMany(PayrollAdjustment::class);
    }

    /** @return BelongsTo<Entity, $this> */
    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    /** @return BelongsTo<SalaryHead, $this> */
    public function head(): BelongsTo
    {
        return $this->belongsTo(SalaryHead::class, 'salary_head_id');
    }
}
