<?php

namespace App\Modules\Payroll\Models;

use App\Core\Models\TenantModel;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Scopes\VisibleToCurrentUser;
use App\Modules\Payroll\Database\Factories\AttendanceCorrectionFactory;
use App\Modules\Payroll\Services\AttendanceReprocessor;
use App\Modules\Workflow\Concerns\HasApprovals;
use App\Modules\Workflow\Contracts\Approvable;
use App\Modules\Workflow\Contracts\ShowsApprovalDetails;
use App\Modules\Workflow\Enums\ApprovalStatus;
use App\Modules\Workflow\Models\Approval;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * "On 3 December I came at 09:00 and left at 18:00" (legacy `attcorrections`). [TA-06]
 * Asked by the employee (Self Service) or by HR for them; goes through the approval type "attendance_correction".
 * Once APPROVED, its times replace the first / last punch of that day (AttendanceInputs → AttendanceProcessor),
 * and the day is processed again. An empty time keeps the punch, e.g. only the forgotten "out".
 *
 *   pending    waiting for approvers → read-only
 *   review     sent back             → the requester changes it and sends it again
 *   approved   approved_at is set    → counts for attendance
 *   rejected                         → stays as history, never counts
 *
 * Several approved corrections for one day: the newest approval wins.
 *
 * @property int $id
 * @property int $employee_id
 * @property Carbon $date
 * @property Carbon|null $requested_in_at
 * @property Carbon|null $requested_out_at
 * @property string $reason
 * @property int|null $requested_by_user_id
 * @property Carbon|null $approved_at
 * @property Carbon $created_at
 */
#[UseFactory(AttendanceCorrectionFactory::class)]
class AttendanceCorrection extends TenantModel implements Approvable, ShowsApprovalDetails
{
    use HasApprovals;

    /** @use HasFactory<AttendanceCorrectionFactory> */
    use HasFactory;

    /** approved_at is NOT fillable: only onApproved() may set it. */
    protected $fillable = ['employee_id', 'date', 'requested_in_at', 'requested_out_at', 'reason', 'requested_by_user_id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['date' => 'date', 'requested_in_at' => 'datetime', 'requested_out_at' => 'datetime', 'approved_at' => 'datetime'];
    }

    /** "COR-0007": how people refer to a correction (lists, toasts, the approver's inbox). */
    public function number(): string
    {
        return 'COR-'.str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }

    /** Waiting for an answer, or sent back: counts as "open" (one open correction per employee and day). */
    public function isOpen(): bool
    {
        return in_array($this->approvalStatus(), [ApprovalStatus::Pending, ApprovalStatus::Review], true);
    }

    /** Only a correction sent back can be changed (and is then sent again). */
    public function isEditable(): bool
    {
        return $this->approvalStatus() === ApprovalStatus::Review;
    }

    /**
     * Pending / Approved / Sent back / Rejected as one badge. Eager load "approvals" in lists.
     *
     * @return array{0: string, 1: string} [label, colour for x-ui.badge]
     */
    public function statusBadge(): array
    {
        $status = $this->approvalStatus();

        return $status ? [$status->label(), $status->color()] : [__('Draft'), 'neutral'];
    }

    /** "09:00", "06:00 (+1)" for the next morning, "—" for "keep the punch". */
    public static function timeLabel(?Carbon $at, Carbon $date): string
    {
        if ($at === null) {
            return '—';
        }

        return $at->format('H:i').($at->isSameDay($date) ? '' : ' (+1)');
    }

    /**
     * withoutGlobalScope: the approver (e.g. a supervisor without an HR role) must see whom the correction is for.
     *
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withoutGlobalScope(VisibleToCurrentUser::class);
    }

    /** The processed day it corrects, if that day was processed already. */
    public function attendanceDay(): ?AttendanceDay
    {
        return AttendanceDay::query()->where('employee_id', $this->employee_id)->whereDate('date', $this->date->toDateString())->first();
    }

    // ---- Approvable ------------------------------------------------------------------------

    public function approvalType(): string
    {
        return 'attendance_correction';
    }

    public function approvalSubject(): string
    {
        return __('Attendance correction :no: :name, :date (in :in, out :out)', [
            'no' => $this->number(),
            'name' => $this->employee->name ?? '?',
            'date' => format_date($this->date),
            'in' => self::timeLabel($this->requested_in_at, $this->date),
            'out' => self::timeLabel($this->requested_out_at, $this->date),
        ]);
    }

    public function approvalContext(): array
    {
        return [
            'employee_id' => $this->employee_id,
            'org_unit_id' => $this->employee?->org_unit_id,
            'employment_type_id' => $this->employee?->employment_type_id,
        ];
    }

    /**
     * From now on the times count. The day is processed again, and the rest of its month too: the monthly
     * grace count of the later days may change with it.
     */
    public function onApproved(Approval $final): void
    {
        $this->forceFill(['approved_at' => now()])->save();

        app(AttendanceReprocessor::class)->queue([$this->employee_id], $this->date, $this->date->copy()->endOfMonth());
    }

    /** Nothing to undo: a rejected correction never counted. */
    public function onRejected(Approval $final): void {}

    /** The approver sees the processed day, the punches and the requested times side by side. */
    public function approvalDetailsView(): string
    {
        return 'payroll::approvals.correction';
    }
}
