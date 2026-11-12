<?php

namespace App\Modules\Hrm\Models;

use App\Core\Models\TenantModel;
use App\Modules\Hrm\Data\LeaveYear;
use App\Modules\Hrm\Enums\LeaveEntryType;
use App\Modules\Hrm\Scopes\VisibleToCurrentUser;
use App\Modules\Hrm\Services\LeaveLedger;
use App\Modules\Workflow\Concerns\HasApprovals;
use App\Modules\Workflow\Contracts\Approvable;
use App\Modules\Workflow\Contracts\ShowsApprovalDetails;
use App\Modules\Workflow\Enums\ApprovalStatus;
use App\Modules\Workflow\Models\Approval;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * "Pay me 5 unused days of Earned leave" (legacy `leaveencash`). Approval type "leave_encashment". [LV-06]
 * Written only by Actions\RequestEncashment.
 *
 *   approved   → onApproved(): the days leave the balance (ledger Encashed, source = this) → ready for payroll
 *   withdrawn  → before payroll paid it: ledger reversed, pending approvals removed
 *   paid       → the payroll run (Phase 10) sets amount, payroll_run_id, paid_at
 *
 * @property int $id
 * @property int $employee_id
 * @property int $leave_type_id
 * @property int $leave_year
 * @property float $days
 * @property string|null $reason
 * @property string|null $amount
 * @property int|null $payroll_run_id
 * @property Carbon|null $paid_at
 * @property int|null $requested_by_user_id
 * @property Carbon|null $approved_at
 * @property Carbon|null $rejected_at
 * @property Carbon|null $withdrawn_at
 * @property Carbon $created_at
 */
class LeaveEncashment extends TenantModel implements Approvable, ShowsApprovalDetails
{
    use HasApprovals;

    protected $fillable = ['employee_id', 'leave_type_id', 'leave_year', 'days', 'reason', 'requested_by_user_id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'days' => 'float',
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'withdrawn_at' => 'datetime',
        ];
    }

    /** "EN-0003". */
    public function number(): string
    {
        return 'EN-'.str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }

    public function isActive(): bool
    {
        return $this->rejected_at === null && $this->withdrawn_at === null;
    }

    public function isEditable(): bool
    {
        return $this->isActive() && $this->approvalStatus() === ApprovalStatus::Review;
    }

    /** @param  Builder<self>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('rejected_at')->whereNull('withdrawn_at');
    }

    /**
     * Approved, not withdrawn, not paid yet: what the payroll run (Phase 10) picks up.
     *
     * @param  Builder<self>  $query
     */
    public function scopeReadyForPayroll(Builder $query): void
    {
        $query->active()->whereNotNull('approved_at')->whereNull('payroll_run_id');
    }

    /** @return array{0: string, 1: string} [label, x-ui.badge colour]. Eager load "approvals" in lists. */
    public function statusBadge(): array
    {
        return match (true) {
            $this->withdrawn_at !== null => [__('Withdrawn'), 'neutral'],
            $this->paid_at !== null => [__('Paid'), 'brand'],
            $this->approved_at !== null => [__('Approved · waiting for payroll'), 'success'],
            default => ($status = $this->approvalStatus()) ? [$status->label(), $status->color()] : [__('Draft'), 'neutral'],
        };
    }

    /** @return BelongsTo<Employee, $this> the approver must see whom it is for */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withoutGlobalScope(VisibleToCurrentUser::class);
    }

    /** @return BelongsTo<LeaveType, $this> */
    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    // ---- Approvable ------------------------------------------------------------------------

    public function approvalType(): string
    {
        return 'leave_encashment';
    }

    public function approvalSubject(): string
    {
        return __('Leave encashment :no: :name, :days days of :type', [
            'no' => $this->number(),
            'name' => $this->employee->name ?? '?',
            'days' => LeaveApplication::daysLabel($this->days),
            'type' => $this->leaveType->name ?? '?',
        ]);
    }

    public function approvalContext(): array
    {
        return [
            'employee_id' => $this->employee_id,
            'org_unit_id' => $this->employee?->org_unit_id,
            'employment_type_id' => $this->employee?->employment_type_id,
            'amount' => $this->days,
        ];
    }

    /** The days leave the balance now (in the approval's transaction); payroll pays them later. */
    public function onApproved(Approval $final): void
    {
        $this->forceFill(['approved_at' => now()])->save();

        app(LeaveLedger::class)->debit($this->employee, $this->leaveType, LeaveYear::of($this->leave_year), $this->days,
            LeaveEntryType::Encashed, source: $this, note: $this->number());
    }

    public function onRejected(Approval $final): void
    {
        $this->forceFill(['rejected_at' => now()])->save();
    }

    public function approvalDetailsView(): string
    {
        return 'hrm::approvals.leave-encashment';
    }
}
