<?php

namespace App\Modules\Payroll\Models;

use App\Core\Models\TenantModel;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Scopes\VisibleToCurrentUser;
use App\Modules\Payroll\Database\Factories\OvertimeRequestFactory;
use App\Modules\Payroll\Services\AttendanceReprocessor;
use App\Modules\Workflow\Concerns\HasApprovals;
use App\Modules\Workflow\Contracts\Approvable;
use App\Modules\Workflow\Contracts\AsksApproverForAmount;
use App\Modules\Workflow\Contracts\ShowsApprovalDetails;
use App\Modules\Workflow\Enums\ApprovalStatus;
use App\Modules\Workflow\Models\Approval;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * "Pay me 3 hours of overtime for 3 December" (legacy `overtimes`). [TA-07]
 * Asked by the employee (Self Service) or by HR / a manager for them; approval type "overtime".
 *
 * Every approver enters the hours when approving (AsksApproverForAmount → approvals.amount):
 *   hours               asked
 *   recommended_hours   the number of the level before the last (null in a one-level workflow)
 *   approved_hours      the last level's number: what is paid
 *
 * Approved hours go to the day's attendance_days.ot_approved_minutes (AttendanceService), which payroll pays
 * at the rate of the day type (Workday / Weekend / Holiday / Festival). One request per employee and day
 * (rejected ones aside).
 *
 * @property int $id
 * @property int $employee_id
 * @property Carbon $date
 * @property int|null $shift_id
 * @property string $hours
 * @property string|null $recommended_hours
 * @property string|null $approved_hours
 * @property string|null $detail
 * @property int|null $requested_by_user_id
 * @property Carbon|null $approved_at
 * @property Carbon $created_at
 */
#[UseFactory(OvertimeRequestFactory::class)]
class OvertimeRequest extends TenantModel implements Approvable, AsksApproverForAmount, ShowsApprovalDetails
{
    use HasApprovals;

    /** @use HasFactory<OvertimeRequestFactory> */
    use HasFactory;

    /** Most hours one request may ask (a whole day). */
    public const MAX_HOURS = 24;

    /** recommended_hours / approved_hours / approved_at are NOT fillable: only onApproved() sets them. */
    protected $fillable = ['employee_id', 'date', 'shift_id', 'hours', 'detail', 'requested_by_user_id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'hours' => 'decimal:2',
            'recommended_hours' => 'decimal:2',
            'approved_hours' => 'decimal:2',
            'approved_at' => 'datetime',
        ];
    }

    /** "OT-0007": how people refer to a request. */
    public function number(): string
    {
        return 'OT-'.str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }

    /** "2.5" for 2.50, "—" for null: how hours are shown. */
    public static function hoursLabel(string|float|null $hours): string
    {
        return $hours === null ? '—' : rtrim(rtrim(number_format((float) $hours, 2, '.', ''), '0'), '.');
    }

    /** Not rejected: counts against "one request per employee and day". */
    public function isActive(): bool
    {
        return $this->approvalStatus() !== ApprovalStatus::Rejected;
    }

    /** Only a request that was sent back can be changed (and is then sent again). */
    public function isEditable(): bool
    {
        return $this->approvalStatus() === ApprovalStatus::Review;
    }

    /** @return array{0: string, 1: string} [label, colour for x-ui.badge]. Eager load "approvals" in lists. */
    public function statusBadge(): array
    {
        $status = $this->approvalStatus();

        return $status ? [$status->label(), $status->color()] : [__('Draft'), 'neutral'];
    }

    /**
     * The newest number an approver entered in the CURRENT round (after the last send-back), before $before's level.
     * While it is waiting: what the levels so far recommend.
     */
    public function recommendedHours(?Approval $before = null): ?float
    {
        $rows = $this->relationLoaded('approvals') ? $this->approvals : $this->approvals()->get();
        $lastSendBack = $rows->where('status', ApprovalStatus::Review)->max('id') ?? 0;
        $row = $rows->where('status', ApprovalStatus::Approved)->whereNotNull('amount')
            ->where('id', '>', $lastSendBack)
            ->when($before, fn ($c) => $c->where('id', '!=', $before->id)->where('level', '<', $before->level))
            ->sortByDesc('level')->first();

        return $row === null ? null : (float) $row->amount;
    }

    /**
     * withoutGlobalScope: the approver (e.g. a supervisor) must see whom the request is for.
     *
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withoutGlobalScope(VisibleToCurrentUser::class);
    }

    /** @return BelongsTo<Shift, $this> */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    /** The processed day, if it was processed already (worked overtime for the approver). */
    public function attendanceDay(): ?AttendanceDay
    {
        return AttendanceDay::query()->where('employee_id', $this->employee_id)->whereDate('date', $this->date->toDateString())->first();
    }

    // ---- Approvable ------------------------------------------------------------------------

    public function approvalType(): string
    {
        return 'overtime';
    }

    public function approvalSubject(): string
    {
        return __('Overtime :no: :name, :date, :hours h', [
            'no' => $this->number(),
            'name' => $this->employee->name ?? '?',
            'date' => format_date($this->date),
            'hours' => self::hoursLabel($this->hours),
        ]);
    }

    public function approvalContext(): array
    {
        return [
            'employee_id' => $this->employee_id,
            'org_unit_id' => $this->employee?->org_unit_id,
            'employment_type_id' => $this->employee?->employment_type_id,
            'amount' => (float) $this->hours,   // a workflow can start from "more than 4 hours" (min_amount)
        ];
    }

    /** The last level's number is what is paid; the day is processed again to carry it to attendance_days. */
    public function onApproved(Approval $final): void
    {
        $this->forceFill([
            'recommended_hours' => $this->recommendedHours($final),
            'approved_hours' => $final->amount ?? $this->hours,
            'approved_at' => now(),
        ])->save();

        app(AttendanceReprocessor::class)->queue([$this->employee_id], $this->date, $this->date);
    }

    /** Nothing to undo: rejected overtime was never paid. */
    public function onRejected(Approval $final): void {}

    // ---- AsksApproverForAmount -------------------------------------------------------------

    public function approverAmountLabel(): string
    {
        return __('Hours');
    }

    public function approverAmountDefault(): float
    {
        return $this->recommendedHours() ?? (float) $this->hours;
    }

    public function approverAmountMax(): float
    {
        return self::MAX_HOURS;
    }

    public function approvalDetailsView(): string
    {
        return 'payroll::approvals.overtime';
    }
}
