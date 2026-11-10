<?php

namespace App\Modules\Payroll\Models;

use App\Core\Models\TenantModel;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\OrgUnit;
use App\Modules\Hrm\Scopes\VisibleToCurrentUser;
use App\Modules\Payroll\Database\Factories\RosterFactory;
use App\Modules\Workflow\Concerns\HasApprovals;
use App\Modules\Workflow\Contracts\Approvable;
use App\Modules\Workflow\Enums\ApprovalStatus;
use App\Modules\Workflow\Models\Approval;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
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
 * @property string $title
 * @property Carbon $from_date
 * @property Carbon $to_date
 * @property string|null $note
 * @property int|null $requested_by_user_id
 * @property Carbon|null $approved_at
 */
#[UseFactory(RosterFactory::class)]
class Roster extends TenantModel implements Approvable
{
    use HasApprovals;

    /** @use HasFactory<RosterFactory> */
    use HasFactory;

    /** approved_at is NOT fillable: only onApproved() may set it. */
    protected $fillable = ['org_unit_id', 'title', 'from_date', 'to_date', 'note', 'requested_by_user_id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['from_date' => 'date', 'to_date' => 'date', 'approved_at' => 'datetime'];
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

    /** @return list<Carbon> every date of the roster, from_date … to_date */
    public function dates(): array
    {
        $dates = [];
        for ($d = $this->from_date->copy(); $d->lte($this->to_date); $d = $d->addDay()) {
            $dates[] = $d->copy();
        }

        return $dates;
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

    /** From now on the plan counts for attendance. forceFill: approved_at is not fillable. */
    public function onApproved(Approval $final): void
    {
        $this->forceFill(['approved_at' => now()])->save();
    }

    /** Nothing to undo: a roster that was never approved never counted. */
    public function onRejected(Approval $final): void {}
}
