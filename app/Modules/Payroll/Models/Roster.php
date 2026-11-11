<?php

namespace App\Modules\Payroll\Models;

use App\Core\Models\TenantModel;
use App\Models\User;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\OrgUnit;
use App\Modules\Hrm\Scopes\VisibleToCurrentUser;
use App\Modules\Hrm\Services\EmployeeVisibility;
use App\Modules\Payroll\Database\Factories\RosterFactory;
use App\Modules\Payroll\Services\AttendanceReprocessor;
use App\Modules\Workflow\Concerns\HasApprovals;
use App\Modules\Workflow\Contracts\Approvable;
use App\Modules\Workflow\Contracts\ShowsApprovalDetails;
use App\Modules\Workflow\Enums\ApprovalStatus;
use App\Modules\Workflow\Models\Approval;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A plan of who works which shift on which day, for one org unit and a date range. [TA-03]
 * The cells are RosterItems. Goes through an approval workflow (approval type "roster");
 * only approved rosters count for attendance.
 *
 *   draft      never sent            → editable, deletable
 *   pending    waiting for approvers → read-only
 *   review     sent back             → editable again, then re-sent
 *   approved   approved_at is set    → read-only (changes: 7.2d)
 *
 * @property int $id
 * @property int $org_unit_id
 * @property int|null $replaces_roster_id
 * @property string $title
 * @property Carbon $from_date
 * @property Carbon $to_date
 * @property string|null $note
 * @property int|null $requested_by_user_id
 * @property Carbon|null $approved_at
 */
#[UseFactory(RosterFactory::class)]
class Roster extends TenantModel implements Approvable, ShowsApprovalDetails
{
    use HasApprovals;

    /** @use HasFactory<RosterFactory> */
    use HasFactory;

    /** approved_at is NOT fillable: only onApproved() may set it. */
    protected $fillable = ['org_unit_id', 'replaces_roster_id', 'title', 'from_date', 'to_date', 'note', 'requested_by_user_id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['from_date' => 'date', 'to_date' => 'date', 'approved_at' => 'datetime'];
    }

    /**
     * Only rosters of the org units $user can see (their role's unit + all units below; no limit = all).
     * Same rule as employees and manpower requests (EmployeeVisibility).
     *
     * @param  Builder<self>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $unitIds = app(EmployeeVisibility::class)->orgUnitIdsFor($user);
        if ($unitIds !== null) {
            $query->whereIn('org_unit_id', $unitIds);
        }
    }

    /** "RST-0007": how people refer to a roster (lists, toasts, approver's inbox). */
    public function number(): string
    {
        return 'RST-'.str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }

    /** Can still be changed: a draft (never sent) or sent back for changes. */
    public function isEditable(): bool
    {
        return in_array($this->approvalStatus(), [null, ApprovalStatus::Review], true);
    }

    /** Can be deleted: only a draft. Once sent, the approval history must stay. */
    public function isDraft(): bool
    {
        return $this->approvalStatus() === null;
    }

    public function isApproved(): bool
    {
        return $this->approved_at !== null;
    }

    /**
     * Draft / Pending / Approved / … as one badge. Eager load "approvals" in lists.
     *
     * @return array{0: string, 1: string} [label, colour for x-ui.badge]
     */
    public function statusBadge(): array
    {
        $status = $this->approvalStatus();

        return $status ? [$status->label(), $status->color()] : [__('Draft'), 'neutral'];
    }

    /** @return list<Carbon> every date of the roster, from_date … to_date */
    public function dates(): array
    {
        $dates = [];
        for ($d = $this->from_date->copy(); $d->lte($this->to_date); $d = $d->addDay()) {
            $dates[] = $d->copy();
        }

        return $dates;
    }

    /**
     * The people in the grid: active employees of the org unit and every unit below it, plus anyone who
     * already has a cell (e.g. moved to another unit since). The visibility scope applies by itself.
     *
     * @return Collection<int, Employee>
     */
    public function gridEmployees(): Collection
    {
        $unitIds = [$this->org_unit_id, ...($this->orgUnit?->descendantIds() ?? [])];
        $planned = $this->items()->distinct()->pluck('employee_id')->all();

        return Employee::query()
            ->where(fn (Builder $q) => $q->where(fn (Builder $q) => $q->active()->whereIn('org_unit_id', $unitIds))->orWhereIn('id', $planned))
            ->orderBy('code')
            ->get(['id', 'code', 'first_name', 'last_name', 'org_unit_id', 'shift_id', 'weekend_profile_id', 'holiday_profile_id']);
    }

    /**
     * Every cell of the grid as text, for every employee and every date (empty = not planned):
     *   [employee_id => ['2026-12-01' => '3' (a shift id) | 'off' | 'call' | '', ...]]
     * Always the FULL matrix, so in the browser every row is an object with all dates.
     *
     * @param  Collection<int, Employee>|null  $employees
     * @return array<int, array<string, string>>
     */
    public function cellMatrix(?Collection $employees = null): array
    {
        $employees ??= $this->gridEmployees();
        $empty = array_fill_keys(array_map(fn (Carbon $d) => $d->toDateString(), $this->dates()), '');

        $matrix = [];
        foreach ($employees as $employee) {
            $matrix[$employee->id] = $empty;
        }
        foreach ($this->items()->get() as $item) {
            if (isset($matrix[$item->employee_id][$item->date->toDateString()])) {
                $matrix[$item->employee_id][$item->date->toDateString()] = $item->cellValue();
            }
        }

        return $matrix;
    }

    /** @return BelongsTo<self, $this> the approved roster this one changes ("Plan a change") */
    public function replaces(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaces_roster_id');
    }

    /** @return BelongsTo<OrgUnit, $this> */
    public function orgUnit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class);
    }

    /** @return HasMany<RosterItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(RosterItem::class);
    }

    /** The requester's employee record, if their login is linked to one (for Supervisor approvers). */
    public function requesterEmployee(): ?Employee
    {
        return $this->requested_by_user_id === null ? null
            : Employee::withoutGlobalScope(VisibleToCurrentUser::class)->where('user_id', $this->requested_by_user_id)->first();
    }

    // ---- Approvable ------------------------------------------------------------------------

    public function approvalType(): string
    {
        return 'roster';
    }

    public function approvalSubject(): string
    {
        return __('Roster :no: :title (:from – :to)', [
            'no' => $this->number(),
            'title' => $this->title,
            'from' => format_date($this->from_date),
            'to' => format_date($this->to_date),
        ]);
    }

    public function approvalContext(): array
    {
        return [
            'employee_id' => $this->requesterEmployee()?->id,
            'org_unit_id' => $this->org_unit_id,
        ];
    }

    /**
     * From now on the plan counts for attendance. forceFill: approved_at is not fillable.
     * Days of the roster that are already past are processed again (7.5), for everyone in this plan and in the
     * plan it replaces (someone taken out of the roster goes back to their own shift).
     */
    public function onApproved(Approval $final): void
    {
        $this->forceFill(['approved_at' => now()])->save();

        $employeeIds = RosterItem::query()->whereIn('roster_id', array_filter([$this->id, $this->replaces_roster_id]))
            ->distinct()->pluck('employee_id');
        $old = $this->replaces;
        app(AttendanceReprocessor::class)->queue(
            $employeeIds,
            $old ? $this->from_date->min($old->from_date) : $this->from_date,
            $old ? $this->to_date->max($old->to_date) : $this->to_date,
        );
    }

    /** Nothing to undo: a roster that was never approved never counted. */
    public function onRejected(Approval $final): void {}

    /** The approver sees the plan itself (read-only grid). */
    public function approvalDetailsView(): string
    {
        return 'payroll::approvals.roster';
    }
}
