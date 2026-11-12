<?php

namespace App\Modules\Hrm\Models;

use App\Core\Models\TenantModel;
use App\Modules\Hrm\Data\LeaveYear;
use App\Modules\Hrm\Enums\LeaveEntryType;
use App\Modules\Hrm\Scopes\VisibleToCurrentUser;
use App\Modules\Hrm\Services\LeaveLedger;
use App\Modules\Payroll\Services\AttendanceReprocessor;
use App\Modules\Workflow\Concerns\HasApprovals;
use App\Modules\Workflow\Contracts\Approvable;
use App\Modules\Workflow\Contracts\ShowsApprovalDetails;
use App\Modules\Workflow\Enums\ApprovalStatus;
use App\Modules\Workflow\Models\Approval;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * "I take Casual leave from 1 to 3 December" (legacy `leaves`). Approval type "leave". [LV-04]
 * Written ONLY by Actions\ApplyForLeave (new / changed after a send-back) and Actions\WithdrawLeave.
 *
 *   approved  → onApproved(): days taken from the balance (LeaveLedger, source = this), attendance processed again
 *   rejected  → onRejected(): rejected_at; nothing was taken
 *   withdrawn → WithdrawLeave: ledger rows reversed, pending approvals removed, attendance processed again
 *
 * The counted dates are in leave_application_days (leaveDays()), not simply from_date–to_date.
 *
 * @property int $id
 * @property int $employee_id
 * @property int $leave_type_id
 * @property Carbon $from_date
 * @property Carbon $to_date
 * @property bool $is_half_day
 * @property float $days
 * @property int $leave_year
 * @property int|null $delegatee_id
 * @property string|null $contact_during_leave
 * @property string|null $reason
 * @property string|null $attachment_path
 * @property string|null $attachment_name
 * @property int|null $requested_by_user_id
 * @property Carbon|null $approved_at
 * @property Carbon|null $rejected_at
 * @property Carbon|null $withdrawn_at
 * @property int|null $withdrawn_by_user_id
 * @property string|null $withdraw_reason
 * @property Carbon $created_at
 */
class LeaveApplication extends TenantModel implements Approvable, ShowsApprovalDetails
{
    use HasApprovals;

    /** The decision stamps are NOT fillable: only onApproved / onRejected / WithdrawLeave set them. */
    protected $fillable = [
        'employee_id', 'leave_type_id', 'from_date', 'to_date', 'is_half_day', 'days', 'leave_year',
        'delegatee_id', 'contact_during_leave', 'reason', 'attachment_path', 'attachment_name', 'requested_by_user_id',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'from_date' => 'date',
            'to_date' => 'date',
            'is_half_day' => 'boolean',
            'days' => 'float',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'withdrawn_at' => 'datetime',
        ];
    }

    /** "LV-0007": how people refer to an application. */
    public function number(): string
    {
        return 'LV-'.str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }

    /** Holds its days: not rejected, not withdrawn (pending, sent back or approved). */
    public function isActive(): bool
    {
        return $this->rejected_at === null && $this->withdrawn_at === null;
    }

    /** Only an application that was sent back can be changed (and is then sent again). */
    public function isEditable(): bool
    {
        return $this->isActive() && $this->approvalStatus() === ApprovalStatus::Review;
    }

    /** @param  Builder<self>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('rejected_at')->whereNull('withdrawn_at');
    }

    /** "1 Dec 2026 – 3 Dec 2026", "1 Dec 2026 (half day)". */
    public function datesLabel(): string
    {
        if ($this->is_half_day) {
            return __(':date (half day)', ['date' => format_date($this->from_date)]);
        }

        return $this->from_date->isSameDay($this->to_date)
            ? format_date($this->from_date)
            : format_date($this->from_date).' – '.format_date($this->to_date);
    }

    /** "2.5" for 2.50. */
    public static function daysLabel(float|string|null $days): string
    {
        return $days === null ? '—' : rtrim(rtrim(number_format((float) $days, 2, '.', ''), '0'), '.');
    }

    /** @return array{0: string, 1: string} [label, x-ui.badge colour]. Eager load "approvals" in lists. */
    public function statusBadge(): array
    {
        if ($this->withdrawn_at !== null) {
            return [__('Withdrawn'), 'neutral'];
        }
        $status = $this->approvalStatus();

        return $status ? [$status->label(), $status->color()] : [__('Draft'), 'neutral'];
    }

    /**
     * withoutGlobalScope: the approver (e.g. a supervisor) must see whom the application is for.
     *
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withoutGlobalScope(VisibleToCurrentUser::class);
    }

    /** @return BelongsTo<LeaveType, $this> */
    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    /** @return BelongsTo<Employee, $this> */
    public function delegatee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'delegatee_id')->withoutGlobalScope(VisibleToCurrentUser::class);
    }

    /** @return HasMany<LeaveApplicationDay, $this> */
    public function leaveDays(): HasMany
    {
        return $this->hasMany(LeaveApplicationDay::class)->orderBy('date');
    }

    /**
     * First and last counted date (sandwich days can lie outside from–to).
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function span(): array
    {
        $dates = $this->leaveDays()->pluck('date');

        return [CarbonImmutable::parse($dates->min() ?? $this->from_date), CarbonImmutable::parse($dates->max() ?? $this->to_date)];
    }

    /** Asks attendance to process the counted days again (only past days are processed). */
    public function reprocessAttendance(): void
    {
        [$first, $last] = $this->span();
        app(AttendanceReprocessor::class)->queue([$this->employee_id], $first, $last);
    }

    // ---- Approvable ------------------------------------------------------------------------

    public function approvalType(): string
    {
        return 'leave';
    }

    public function approvalSubject(): string
    {
        return __('Leave :no: :name, :type, :dates (:days days)', [
            'no' => $this->number(),
            'name' => $this->employee->name ?? '?',
            'type' => $this->leaveType->name ?? '?',
            'dates' => $this->datesLabel(),
            'days' => self::daysLabel($this->days),
        ]);
    }

    public function approvalContext(): array
    {
        return [
            'employee_id' => $this->employee_id,
            'org_unit_id' => $this->employee?->org_unit_id,
            'employment_type_id' => $this->employee?->employment_type_id,
            'amount' => $this->days,   // a workflow can start from "more than 3 days" (min_amount)
        ];
    }

    /** Takes the days from the balance (inside the approval's transaction), then processes attendance again. */
    public function onApproved(Approval $final): void
    {
        $this->forceFill(['approved_at' => now()])->save();

        app(LeaveLedger::class)->debit(
            $this->employee, $this->leaveType, LeaveYear::of($this->leave_year), $this->days, LeaveEntryType::Taken,
            $this->span()[0], source: $this, note: $this->number(),
        );

        $this->reprocessAttendance();
    }

    public function onRejected(Approval $final): void
    {
        $this->forceFill(['rejected_at' => now()])->save();
    }

    public function approvalDetailsView(): string
    {
        return 'hrm::approvals.leave';
    }
}
