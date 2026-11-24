<?php

namespace App\Modules\Payroll\Models;

use App\Core\Casts\MoneyCast;
use App\Core\Models\TenantModel;
use App\Core\Support\Money;
use App\Core\Support\Period;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Scopes\VisibleToCurrentUser;
use App\Modules\Payroll\Database\Factories\PayrollAdjustmentFactory;
use App\Modules\Payroll\Enums\HeadType;
use App\Modules\Workflow\Concerns\HasApprovals;
use App\Modules\Workflow\Contracts\Approvable;
use App\Modules\Workflow\Contracts\ShowsApprovalDetails;
use App\Modules\Workflow\Enums\ApprovalStatus;
use App\Modules\Workflow\Models\Approval;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A one-time earning or deduction for one employee in one month (legacy `adjustments`), e.g. "Shift allowance
 * 2,000" or "Canteen 500". The head's type says earning or deduction; the amount is always positive.
 * Written ONLY by Actions\RequestAdjustment (form and Excel import); approval type "payroll_adjustment".
 *
 *   approved  → onApproved(): approved_at; the next regular run of that month pays it
 *   rejected  → onRejected(): rejected_at
 *   withdrawn → RequestAdjustment::withdraw(): withdrawn_at, pending approvals removed
 *   taken     → payroll_run_id: the Adjustments stage (10.6) took it; calculating the run again keeps it,
 *               a failed / deleted run frees it again
 *
 * @property int $id
 * @property int $employee_id
 * @property int $salary_head_id
 * @property int $year
 * @property int $month
 * @property Money $amount
 * @property string $reason
 * @property int|null $payroll_run_id the run that took it; NULL = not taken yet
 * @property int|null $requested_by_user_id
 * @property Carbon|null $approved_at
 * @property Carbon|null $rejected_at
 * @property Carbon|null $withdrawn_at
 * @property int|null $withdrawn_by_user_id
 * @property Carbon $created_at
 * @property-read SalaryHead $head
 * @property-read Employee|null $employee
 * @property-read PayrollRun|null $run
 */
#[UseFactory(PayrollAdjustmentFactory::class)]
class PayrollAdjustment extends TenantModel implements Approvable, ShowsApprovalDetails
{
    use HasApprovals;

    /** @use HasFactory<PayrollAdjustmentFactory> */
    use HasFactory;

    /** payroll_run_id and the decision stamps are NOT fillable: only the run / the approval / withdraw set them. */
    protected $fillable = ['employee_id', 'salary_head_id', 'year', 'month', 'amount', 'reason', 'requested_by_user_id'];

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'amount' => MoneyCast::class,
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'withdrawn_at' => 'datetime',
        ];
    }

    /** "AD-0007". */
    public function number(): string
    {
        return 'AD-'.str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }

    /** The month it is paid in. */
    public function period(): Period
    {
        return Period::of($this->year, $this->month);
    }

    public function isActive(): bool
    {
        return $this->rejected_at === null && $this->withdrawn_at === null;
    }

    /** Only one that was sent back can be changed (and is then sent again). */
    public function isEditable(): bool
    {
        return $this->isActive() && $this->approvalStatus() === ApprovalStatus::Review;
    }

    public function isPaid(): bool
    {
        return $this->payroll_run_id !== null;
    }

    /** +1 000.00 for an earning, −500.00 for a deduction: how lists show it. */
    public function signedAmount(): string
    {
        return ($this->head->type === HeadType::Deduction ? '−' : '+').$this->amount->format();
    }

    /** @param  Builder<self>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('rejected_at')->whereNull('withdrawn_at');
    }

    /**
     * Approved, active adjustments of a month that a run may take: not taken yet, or taken by THIS run already
     * (calculating it again).
     *
     * @param  Builder<self>  $query
     */
    public function scopeDueIn(Builder $query, Period $period, ?PayrollRun $run = null): void
    {
        $query->active()->where('year', $period->year)->where('month', $period->month)->whereNotNull('approved_at')
            ->where(fn (Builder $q) => $q->whereNull('payroll_run_id')->when($run, fn ($q) => $q->orWhere('payroll_run_id', $run->id)));
    }

    /** @return array{0: string, 1: string} [label, x-ui.badge colour]. Eager load "approvals" and "run" in lists. */
    public function statusBadge(): array
    {
        return match (true) {
            $this->withdrawn_at !== null => [__('Withdrawn'), 'neutral'],
            $this->rejected_at !== null => [__('Rejected'), 'danger'],
            $this->payroll_run_id !== null => [__('In :run', ['run' => $this->run->code ?? '?']), 'brand'],
            $this->approved_at !== null => [__('Approved · waiting for payroll'), 'success'],
            default => ($status = $this->approvalStatus()) ? [$status->label(), $status->color()] : [__('Draft'), 'neutral'],
        };
    }

    /** @return BelongsTo<Employee, $this> the approver must see whom it is for */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withoutGlobalScope(VisibleToCurrentUser::class);
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

    // ---- Approvable ------------------------------------------------------------------------

    public function approvalType(): string
    {
        return 'payroll_adjustment';
    }

    public function approvalSubject(): string
    {
        return __('Payroll adjustment :no: :name, :head :amount for :month', [
            'no' => $this->number(),
            'name' => $this->employee->name ?? '?',
            'head' => $this->head->name ?? '?',
            'amount' => $this->signedAmount(),
            'month' => $this->period()->label(),
        ]);
    }

    public function approvalContext(): array
    {
        return [
            'employee_id' => $this->employee_id,
            'entity_ids' => $this->employee?->entityIds() ?? [],
            'employment_type_id' => $this->employee?->employment_type_id,
            'amount' => (float) $this->amount->toDecimal(),
        ];
    }

    public function onApproved(Approval $final): void
    {
        $this->forceFill(['approved_at' => now()])->save();
    }

    public function onRejected(Approval $final): void
    {
        $this->forceFill(['rejected_at' => now()])->save();
    }

    public function approvalDetailsView(): string
    {
        return 'payroll::approvals.adjustment';
    }
}
