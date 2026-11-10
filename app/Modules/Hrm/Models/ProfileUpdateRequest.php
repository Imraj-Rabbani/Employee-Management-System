<?php

namespace App\Modules\Hrm\Models;

use App\Core\Models\TenantModel;
use App\Modules\Hrm\Enums\BloodGroup;
use App\Modules\Hrm\Enums\Gender;
use App\Modules\Hrm\Enums\MaritalStatus;
use App\Modules\Hrm\Enums\Religion;
use App\Modules\Hrm\Scopes\VisibleToCurrentUser;
use App\Modules\Workflow\Concerns\HasApprovals;
use App\Modules\Workflow\Contracts\Approvable;
use App\Modules\Workflow\Contracts\ShowsApprovalDetails;
use App\Modules\Workflow\Models\Approval;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * "Please change my mobile number": an employee's request to change their own personal details,
 * applied only once the workflow approves it (legacy `profileupdaterequests`). [EMP-08]
 *
 *     $request->field_changes          ['mobile' => ['old' => '017…', 'new' => '018…'], ...]
 *     $request->submitForApproval()    into the "profile_update" workflow (HasApprovals)
 *     $request->approvalStatus()       Pending / Approved / Rejected / Review (sent back)
 *
 * @property int $id
 * @property int $employee_id
 * @property int|null $requested_by_user_id
 * @property array<string, array{old: mixed, new: mixed}> $field_changes (not "changes": Eloquent's own $changes property would shadow it inside the class)
 * @property string|null $reason
 * @property Carbon|null $applied_at
 * @property Carbon $created_at
 */
class ProfileUpdateRequest extends TenantModel implements Approvable, ShowsApprovalDetails
{
    use HasApprovals;

    /**
     * Fields an employee may ask to change. Office email and everything on the Job tab are set by the
     * company, not requested.
     */
    public const FIELDS = [
        'title', 'first_name', 'last_name', 'name_local', 'gender', 'birth_date', 'marital_status',
        'blood_group', 'religion', 'nationality', 'national_id', 'tin', 'passport_no', 'mobile', 'personal_email',
    ];

    /** Enum fields: how to turn the stored number into a label. */
    private const ENUMS = [
        'gender' => Gender::class,
        'marital_status' => MaritalStatus::class,
        'blood_group' => BloodGroup::class,
        'religion' => Religion::class,
    ];

    protected $fillable = ['employee_id', 'requested_by_user_id', 'field_changes', 'reason', 'applied_at'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['field_changes' => 'array', 'applied_at' => 'datetime'];
    }

    /**
     * withoutGlobalScope: the approver (e.g. a supervisor without an HR role) must see the employee
     * the request is about, even outside their own org-unit access.
     *
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withoutGlobalScope(VisibleToCurrentUser::class);
    }

    /** "Mobile", "National ID" ... */
    public static function label(string $field): string
    {
        return match ($field) {
            'name_local' => __('Name (local)'),
            'birth_date' => __('Date of birth'),
            'national_id' => __('National ID'),
            'tin' => __('TIN'),
            'passport_no' => __('Passport no.'),
            default => __(ucfirst(str_replace('_', ' ', $field))),
        };
    }

    /** A stored value as people read it: enum label, formatted date, or "—" for empty. */
    public static function display(string $field, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }
        if (isset(self::ENUMS[$field])) {
            return self::ENUMS[$field]::tryFrom((int) $value)?->label() ?? (string) $value;
        }

        return $field === 'birth_date' ? format_date((string) $value) : (string) $value;
    }

    // ---- Approvable ------------------------------------------------------------------------

    public function approvalType(): string
    {
        return 'profile_update';
    }

    public function approvalSubject(): string
    {
        $fields = implode(', ', array_map(fn ($f) => self::label($f), array_keys($this->field_changes)));

        return __('Profile update: :name (:fields)', ['name' => $this->employee->name ?? '?', 'fields' => $fields]);
    }

    public function approvalContext(): array
    {
        return [
            'employee_id' => $this->employee_id,
            'org_unit_id' => $this->employee?->org_unit_id,
            'employment_type_id' => $this->employee?->employment_type_id,
        ];
    }

    /** Last level approved: write the new values. Runs in the engine's transaction (all or nothing). */
    public function onApproved(Approval $final): void
    {
        /** @var Employee $employee */
        $employee = $this->employee()->firstOrFail();
        $employee->update(array_map(fn (array $change) => $change['new'], $this->field_changes));

        $this->update(['applied_at' => now()]);
    }

    /** Nothing to undo: the employee record was never touched. */
    public function onRejected(Approval $final): void {}

    public function approvalDetailsView(): string
    {
        return 'hrm::approvals.profile-update';
    }
}
