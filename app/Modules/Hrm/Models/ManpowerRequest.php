<?php

namespace App\Modules\Hrm\Models;

use App\Core\Models\TenantModel;
use App\Models\User;
use App\Modules\Hrm\Database\Factories\ManpowerRequestFactory;
use App\Modules\Hrm\Enums\JobPostingStatus;
use App\Modules\Hrm\Enums\ManpowerRequestType;
use App\Modules\Hrm\Scopes\VisibleToCurrentUser;
use App\Modules\Hrm\Services\EmployeeVisibility;
use App\Modules\Workflow\Concerns\HasApprovals;
use App\Modules\Workflow\Contracts\Approvable;
use App\Modules\Workflow\Contracts\ShowsApprovalDetails;
use App\Modules\Workflow\Enums\ApprovalStatus;
use App\Modules\Workflow\Models\Approval;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * "We need 2 Sales Officers in Chattogram by 1 January": a request for people, approved through a
 * workflow before HR may advertise it (6.3). [REC-01]
 *
 *     $request->submitForApproval($user)   into the "manpower_request" workflow (HasApprovals)
 *     $request->approvalStatus()           null = draft (not sent yet), Pending, Approved, Rejected, Review
 *     ManpowerRequest::approved()->get()   approved ones (approved_at set), for job postings
 *     $request->openings()                 headcount still to fill
 *
 * @property int $id
 * @property int $org_unit_id
 * @property int $designation_id
 * @property int|null $position_id
 * @property int|null $grade_id
 * @property int|null $employment_type_id
 * @property ManpowerRequestType $type
 * @property int|null $replaces_employee_id
 * @property int $headcount
 * @property int $hired_count
 * @property string|null $salary_min decimal comes back as a string, e.g. "35000.00"
 * @property string|null $salary_max
 * @property Carbon|null $needed_by
 * @property string|null $justification
 * @property int|null $requested_by_user_id
 * @property Carbon|null $approved_at
 * @property Carbon $created_at
 */
#[UseFactory(ManpowerRequestFactory::class)]
class ManpowerRequest extends TenantModel implements Approvable, ShowsApprovalDetails
{
    use HasApprovals;

    /** @use HasFactory<ManpowerRequestFactory> */
    use HasFactory;

    protected $fillable = [
        'org_unit_id', 'designation_id', 'position_id', 'grade_id', 'employment_type_id', 'type',
        'replaces_employee_id', 'headcount', 'salary_min', 'salary_max', 'needed_by', 'justification',
        'requested_by_user_id',
    ];

    protected $attributes = ['hired_count' => 0];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => ManpowerRequestType::class,
            'headcount' => 'integer',
            'hired_count' => 'integer',
            'salary_min' => 'decimal:2',
            'salary_max' => 'decimal:2',
            'needed_by' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    // ---- relations -------------------------------------------------------------------------

    /** @return BelongsTo<OrgUnit, $this> */
    public function orgUnit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class);
    }

    /** @return BelongsTo<Designation, $this> */
    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }

    /** @return BelongsTo<Position, $this> */
    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    /** @return BelongsTo<Grade, $this> */
    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    /** @return BelongsTo<EmploymentType, $this> */
    public function employmentType(): BelongsTo
    {
        return $this->belongsTo(EmploymentType::class);
    }

    /**
     * withoutGlobalScope: the person being replaced may be outside the viewer's org-unit access
     * (e.g. an approver higher up), but the request must still say who it is.
     *
     * @return BelongsTo<Employee, $this>
     */
    public function replacesEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'replaces_employee_id')->withoutGlobalScope(VisibleToCurrentUser::class);
    }

    /** @return HasMany<JobPosting, $this> */
    public function jobPostings(): HasMany
    {
        return $this->hasMany(JobPosting::class);
    }

    // ---- helpers ---------------------------------------------------------------------------

    /** @param  Builder<self>  $query */
    public function scopeApproved(Builder $query): void
    {
        $query->whereNotNull('approved_at');
    }

    /**
     * Requests of the org units this user may see (EmployeeVisibility: role's unit + units below it).
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

    /** Can still be changed: a draft (never sent) or sent back for changes (Review). */
    public function isEditable(): bool
    {
        return in_array($this->approvalStatus(), [null, ApprovalStatus::Review], true);
    }

    /** Can be deleted: only a draft. Once sent, the approval history must stay. */
    public function isDraft(): bool
    {
        return $this->approvalStatus() === null;
    }

    /** "MPR-0007": how people refer to a request (list, toasts, approver's inbox). */
    public function number(): string
    {
        return 'MPR-'.str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }

    /** "30,000 – 40,000", "from 30,000", "up to 40,000" or null when no salary was given. */
    public function salaryRange(): ?string
    {
        $min = $this->salary_min === null ? null : number_format((float) $this->salary_min);
        $max = $this->salary_max === null ? null : number_format((float) $this->salary_max);

        return match (true) {
            $min !== null && $max !== null => $min === $max ? $min : "{$min} – {$max}",
            $min !== null => __('from :amount', ['amount' => $min]),
            $max !== null => __('up to :amount', ['amount' => $max]),
            default => null,
        };
    }

    /**
     * How many openings a (new or edited) posting may still have: the headcount still to fill, minus what
     * Draft / Open postings already advertise. A CLOSED posting no longer counts, so after closing one
     * without hiring everybody, the rest can be advertised again.
     *
     * @param  int|null  $exceptPostingId  the posting being edited: its own openings do not count against it
     */
    public function openingsToAdvertise(?int $exceptPostingId = null): int
    {
        $advertised = (int) $this->jobPostings()
            ->whereIn('status', [JobPostingStatus::Draft, JobPostingStatus::Open])
            ->when($exceptPostingId, fn ($q) => $q->whereKeyNot($exceptPostingId))
            ->sum('openings');

        return max(0, $this->openings() - $advertised);
    }

    /** Headcount still to fill (never below 0). */
    public function openings(): int
    {
        return max(0, $this->headcount - $this->hired_count);
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
        return 'manpower_request';
    }

    public function approvalSubject(): string
    {
        return __('Manpower request :no: :count × :designation (:unit)', [
            'no' => $this->number(),
            'count' => $this->headcount,
            'designation' => $this->designation->name ?? '?',
            'unit' => $this->orgUnit->name ?? '?',
        ]);
    }

    /**
     * employee_id = the REQUESTER's employee record, so a "Supervisor" level means the requester's boss.
     * amount = monthly cost at the top of the salary range (salary_max × headcount), so a workflow with
     * a min_amount can add an extra level for expensive requests.
     */
    public function approvalContext(): array
    {
        return [
            'employee_id' => $this->requesterEmployee()?->id,
            'org_unit_id' => $this->org_unit_id,
            'employment_type_id' => $this->employment_type_id,
            'amount' => $this->salary_max === null ? null : (float) $this->salary_max * $this->headcount,
        ];
    }

    /**
     * Last level approved: HR may now open a job posting for it (6.3).
     * forceFill: approved_at is NOT in $fillable (a form must never set it), and update() would silently skip it.
     */
    public function onApproved(Approval $final): void
    {
        $this->forceFill(['approved_at' => now()])->save();
    }

    /** Nothing to undo: an unapproved request has no postings. */
    public function onRejected(Approval $final): void {}

    /** The approver sees the same details as the request page (approval modal + email page). */
    public function approvalDetailsView(): string
    {
        return 'hrm::approvals.manpower-request';
    }
}
