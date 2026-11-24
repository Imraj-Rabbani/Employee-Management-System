<?php

namespace App\Modules\Hrm\Models;

use App\Core\Models\TenantModel;
use App\Models\User;
use App\Modules\Hrm\Database\Factories\EmployeeFactory;
use App\Modules\Hrm\Enums\BloodGroup;
use App\Modules\Hrm\Enums\EmployeeStatus;
use App\Modules\Hrm\Enums\Gender;
use App\Modules\Hrm\Enums\MaritalStatus;
use App\Modules\Hrm\Enums\PaymentMethod;
use App\Modules\Hrm\Enums\Religion;
use App\Modules\Hrm\Policies\EmployeePolicy;
use App\Modules\Hrm\Scopes\VisibleToCurrentUser;
use App\Modules\Hrm\Services\CreditLeaveNow;
use App\Modules\Payroll\Models\HolidayProfile;
use App\Modules\Payroll\Models\PayScaleProfile;
use App\Modules\Payroll\Models\SalaryProfile;
use App\Modules\Payroll\Models\Shift;
use App\Modules\Payroll\Models\WeekendProfile;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * A person employed by the company (legacy `employees`).
 *
 *     $employee->name                    "Rahim Uddin" (first + last)
 *     $employee->status                  EmployeeStatus::Active (enum, thanks to the cast)
 *     $employee->grade->name             org data through belongsTo relations
 *     $employee->supervisor              another Employee (self-referencing)
 *     $employee->subordinates            employees who report to this one
 *     $employee->entities                current entities: Department Finance, Branch Gulshan ...
 *     $employee->entityAssignments       entity history (all types), newest first
 *     $employee->entityOf($type)         the current entity of one type (Department → Finance)
 *     Employee::inEntities([3, 7])       employees currently in any of these entities
 *     $employee->familyMembers / nominees   family and nominees
 *     $employee->educations / workHistories education and earlier jobs
 *     $employee->documents / bankAccounts   documents with expiry, bank accounts
 *     $employee->user                    the login (central DB), set when the employee is invited
 *     Employee::active()->get()          only Active ones
 *
 * Every query only returns the employees the logged-in user may see: #[ScopedBy] attaches the
 * VisibleToCurrentUser global scope. #[UsePolicy] links EmployeePolicy (view / update / delete rules).
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $code
 * @property string|null $access_code
 * @property string|null $title
 * @property string $first_name
 * @property string|null $last_name
 * @property string|null $name_local
 * @property-read string $name
 * @property-read string|null $photo_url
 * @property Gender|null $gender
 * @property Carbon|null $birth_date
 * @property MaritalStatus|null $marital_status
 * @property BloodGroup|null $blood_group
 * @property Religion|null $religion
 * @property string|null $nationality
 * @property string|null $national_id
 * @property string|null $tin
 * @property string|null $passport_no
 * @property string|null $mobile
 * @property string|null $personal_email
 * @property string|null $office_email
 * @property string|null $photo_path
 * @property string|null $signature_path
 * @property Carbon $join_date
 * @property Carbon|null $planned_confirmation_date
 * @property int|null $confirmation_status
 * @property Carbon|null $confirmation_date
 * @property int|null $employment_type_id
 * @property int|null $designation_id
 * @property int|null $grade_id
 * @property int|null $position_id
 * @property int|null $supervisor_id
 * @property int|null $dotted_line_manager_id
 * @property int|null $shift_id
 * @property bool $has_shifting
 * @property int|null $weekend_profile_id
 * @property int|null $holiday_profile_id
 * @property int|null $leave_profile_id
 * @property int|null $salary_profile_id
 * @property bool $salary_on_hold Payroll: the payroll run leaves the salary unpaid until switched off
 * @property bool $bank_transfer_on_hold Payroll: paid, but not sent to the bank
 * @property string|null $salary_hold_reason
 * @property int|null $pay_scale_profile_id
 * @property int|null $tax_profile_id
 * @property PaymentMethod|null $payment_method
 * @property EmployeeStatus $status
 * @property Carbon|null $separation_date
 */
#[ScopedBy(VisibleToCurrentUser::class)]
#[UseFactory(EmployeeFactory::class)]
#[UsePolicy(EmployeePolicy::class)]
class Employee extends TenantModel
{
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory;

    /**
     * user_id is deliberately not fillable: the login link is made only by the "link user" action,
     * never from a form.
     */
    protected $fillable = [
        'code', 'access_code', 'title', 'first_name', 'last_name', 'name_local',
        'gender', 'birth_date', 'marital_status', 'blood_group', 'religion', 'nationality',
        'national_id', 'tin', 'passport_no',
        'mobile', 'personal_email', 'office_email', 'photo_path', 'signature_path',
        'join_date', 'planned_confirmation_date', 'confirmation_status', 'confirmation_date',
        'employment_type_id', 'designation_id', 'grade_id', 'position_id',
        'supervisor_id', 'dotted_line_manager_id',
        'shift_id', 'has_shifting', 'weekend_profile_id', 'holiday_profile_id', 'leave_profile_id',
        'salary_profile_id', 'salary_on_hold', 'bank_transfer_on_hold', 'salary_hold_reason', 'pay_scale_profile_id', 'tax_profile_id', 'payment_method',
        'status', 'separation_date',
    ];

    /**
     * Profile sections whose rows may have an attachment: URL segment => relation method.
     * Used by the hrm.employees.attachment route (only these can be downloaded).
     */
    public const ATTACHMENT_SECTIONS = [
        'educations' => 'educations',
        'work-histories' => 'workHistories',
        'documents' => 'documents',
    ];

    protected $attributes = [
        'status' => 1,              // EmployeeStatus::Active
        'has_shifting' => false,
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'marital_status' => MaritalStatus::class,
            'blood_group' => BloodGroup::class,
            'religion' => Religion::class,
            'payment_method' => PaymentMethod::class,
            'status' => EmployeeStatus::class,
            'birth_date' => 'date',
            'join_date' => 'date',
            'planned_confirmation_date' => 'date',
            'confirmation_date' => 'date',
            'separation_date' => 'date',
            'has_shifting' => 'boolean',
            'salary_on_hold' => 'boolean',
            'bank_transfer_on_hold' => 'boolean',
        ];
    }

    /**
     * Model events: code Laravel runs after every save, whichever screen or import did it.
     * Getting a leave profile (or a new one, or a new join / separation date) credits the leave that is
     * already due at once (CreditLeaveNow), so the balance shows today instead of after tonight's job.
     */
    protected static function booted(): void
    {
        static::saved(function (Employee $employee) {
            if ($employee->leave_profile_id !== null
                && ($employee->wasRecentlyCreated || $employee->wasChanged(['leave_profile_id', 'join_date', 'separation_date']))) {
                app(CreditLeaveNow::class)->forEmployees([$employee->id]);
            }
        });
    }

    /**
     * "Rahim Uddin". Read as $employee->name (an accessor: a computed attribute, not a column).
     *
     * @return Attribute<string, never>
     */
    protected function name(): Attribute
    {
        return Attribute::get(fn () => trim($this->first_name.' '.$this->last_name));
    }

    /**
     * URL of the photo, or null (then show initials). ?v= changes when the photo changes, so the
     * browser does not keep showing the old cached one. Read as $employee->photo_url.
     *
     * @return Attribute<string|null, never>
     */
    protected function photoUrl(): Attribute
    {
        return Attribute::get(fn () => $this->photo_path === null ? null
            : route('hrm.employees.photo', ['employee' => $this, 'v' => substr(md5($this->photo_path), 0, 8)]));
    }

    /**
     * Validation rules for the personal fields, shared by HR's Personal form and the employee's
     * profile update request, so both check exactly the same. $ignoreId = the employee being changed.
     *
     * @return array<string, list<mixed>>
     */
    public static function personalRules(?int $ignoreId): array
    {
        return [
            'title' => ['nullable', 'string', 'max:20'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'name_local' => ['nullable', 'string', 'max:200'],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'marital_status' => ['nullable', Rule::enum(MaritalStatus::class)],
            'blood_group' => ['nullable', Rule::enum(BloodGroup::class)],
            'religion' => ['nullable', Rule::enum(Religion::class)],
            'nationality' => ['nullable', 'string', 'max:50'],
            // unique ... ignore(): no OTHER employee may have the same number (this one may keep its own).
            'national_id' => ['nullable', 'string', 'max:30', Rule::unique(self::class, 'national_id')->ignore($ignoreId)],
            'tin' => ['nullable', 'string', 'max:30', Rule::unique(self::class, 'tin')->ignore($ignoreId)],
            'passport_no' => ['nullable', 'string', 'max:30'],
            'mobile' => ['nullable', 'string', 'max:30'],
            'personal_email' => ['nullable', 'email', 'max:255'],
            'office_email' => ['nullable', 'email', 'max:255', Rule::unique(self::class, 'office_email')->ignore($ignoreId)],
        ];
    }

    /** @param  Builder<self>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', EmployeeStatus::Active);
    }

    /**
     * Would reporting to $supervisorId create a loop (A reports to B, B reports to ... A)?
     * Walks up the supervisor chain. withoutGlobalScope: the chain may pass through people you cannot see.
     */
    public function wouldLoopUnder(int $supervisorId): bool
    {
        $bosses = static::withoutGlobalScope(VisibleToCurrentUser::class)->pluck('supervisor_id', 'id')->all();
        $current = $supervisorId;
        $seen = [];

        while ($current !== null && ! in_array($current, $seen, true)) {
            if ($current === $this->id) {
                return true;
            }
            $seen[] = $current;
            $current = $bosses[$current] ?? null;
        }

        return false;
    }

    /**
     * Entity history of every type, newest first. Change it only through ChangeEntity.
     *
     * @return HasMany<EmployeeEntity, $this>
     */
    public function entityAssignments(): HasMany
    {
        return $this->hasMany(EmployeeEntity::class)->latestFirst();
    }

    /**
     * The open rows: one per entity type the employee is in.
     *
     * @return HasMany<EmployeeEntity, $this>
     */
    public function currentEntityAssignments(): HasMany
    {
        return $this->hasMany(EmployeeEntity::class)->whereNull('effective_to');
    }

    /**
     * The entities the employee is in NOW (a many-to-many through employee_entities, open rows only).
     *
     * @return BelongsToMany<Entity, $this>
     */
    public function entities(): BelongsToMany
    {
        return $this->belongsToMany(Entity::class, 'employee_entities')
            ->wherePivotNull('effective_to')
            ->withPivot('entity_type_id', 'effective_from');
    }

    /**
     * Ids of the current entities. Uses the loaded relation when there is one.
     *
     * @return list<int>
     */
    public function entityIds(): array
    {
        return $this->relationLoaded('currentEntityAssignments')
            ? $this->currentEntityAssignments->pluck('entity_id')->map(fn ($id) => (int) $id)->values()->all()
            : $this->currentEntityAssignments()->pluck('entity_id')->map(fn ($id) => (int) $id)->all();
    }

    /** The current entity of one type, e.g. entityOf($department) → Finance. Null = none of that type. */
    public function entityOf(EntityType|int $type): ?Entity
    {
        $typeId = $type instanceof EntityType ? $type->id : $type;

        return $this->entities()->wherePivot('entity_type_id', $typeId)->first();
    }

    /**
     * "Finance (Department), Gulshan (Branch)": the current entities on one line, in type order.
     * $withType false → "Finance, Gulshan" (lists). Eager load with('entities.type') in lists.
     */
    public function entitiesLabel(bool $withType = true): string
    {
        $this->loadMissing('entities.type');

        return $this->entities
            ->sortBy(fn (Entity $e) => [$e->type->sequence, $e->type->name])
            ->map(fn (Entity $e) => $withType ? $e->label() : $e->name)
            ->implode(', ');
    }

    /**
     * Employees currently in ANY of these entities. A sub-query, so it works inside other where's:
     * "employees.id IN (SELECT employee_id FROM employee_entities WHERE effective_to IS NULL AND entity_id IN (...))".
     * An empty list matches nobody.
     *
     * @param  Builder<self>  $query
     * @param  list<int>  $entityIds
     */
    public function scopeInEntities(Builder $query, array $entityIds): void
    {
        $query->whereIn($this->qualifyColumn('id'), self::idsInEntities($entityIds));
    }

    /**
     * The sub-query behind inEntities(), for other tables that store an employee_id:
     * $q->whereIn('leave_applications.employee_id', Employee::idsInEntities($ids)).
     *
     * @param  list<int>  $entityIds
     */
    public static function idsInEntities(array $entityIds): \Illuminate\Database\Query\Builder
    {
        return (new EmployeeEntity)->newQuery()->toBase()
            ->select('employee_id')
            ->whereNull('effective_to')
            ->whereIn('entity_id', $entityIds);
    }

    /**
     * Family members: parents first, then spouse, children ... (by relation number), oldest first.
     *
     * @return HasMany<EmployeeFamilyMember, $this>
     */
    public function familyMembers(): HasMany
    {
        return $this->hasMany(EmployeeFamilyMember::class)->orderBy('relation')->orderBy('birth_date');
    }

    /**
     * Nominees, in the order they were added. Their shares must total 100%.
     *
     * @return HasMany<EmployeeNominee, $this>
     */
    public function nominees(): HasMany
    {
        return $this->hasMany(EmployeeNominee::class)->orderBy('id');
    }

    /**
     * Education, newest first.
     *
     * @return HasMany<EmployeeEducation, $this>
     */
    public function educations(): HasMany
    {
        return $this->hasMany(EmployeeEducation::class)->orderByDesc('passing_year')->orderByDesc('id');
    }

    /**
     * Jobs before this company, newest first.
     *
     * @return HasMany<EmployeeWorkHistory, $this>
     */
    public function workHistories(): HasMany
    {
        return $this->hasMany(EmployeeWorkHistory::class)->orderByDesc('from_date')->orderByDesc('id');
    }

    /**
     * Documents: those expiring first on top, documents without expiry last.
     *
     * @return HasMany<EmployeeDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class)->orderByRaw('expiry_date IS NULL')->orderBy('expiry_date')->orderBy('type');
    }

    /**
     * Bank accounts, primary first.
     *
     * @return HasMany<EmployeeBankAccount, $this>
     */
    public function bankAccounts(): HasMany
    {
        return $this->hasMany(EmployeeBankAccount::class)->orderByDesc('is_primary')->orderBy('id');
    }

    /** @return BelongsTo<EmploymentType, $this> */
    public function employmentType(): BelongsTo
    {
        return $this->belongsTo(EmploymentType::class);
    }

    /** @return BelongsTo<Designation, $this> */
    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }

    /** @return BelongsTo<Grade, $this> */
    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    /** @return BelongsTo<Position, $this> */
    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    /**
     * Own shift; NULL = the company's default shift (Shift::default()). Payroll module, 7.1.
     *
     * @return BelongsTo<Shift, $this>
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    /** @return BelongsTo<WeekendProfile, $this> */
    public function weekendProfile(): BelongsTo
    {
        return $this->belongsTo(WeekendProfile::class);
    }

    /** @return BelongsTo<HolidayProfile, $this> */
    public function holidayProfile(): BelongsTo
    {
        return $this->belongsTo(HolidayProfile::class);
    }

    /**
     * Which leave types this employee may take and how many days per year. NULL = no leave set up yet.
     *
     * @return BelongsTo<LeaveProfile, $this>
     */
    public function leaveProfile(): BelongsTo
    {
        return $this->belongsTo(LeaveProfile::class);
    }

    /**
     * Salary structure (Payroll module); the employee's own salary heads are separate. NULL = none.
     *
     * @return BelongsTo<SalaryProfile, $this>
     */
    public function salaryProfile(): BelongsTo
    {
        return $this->belongsTo(SalaryProfile::class);
    }

    /**
     * How payroll treats this employee: arrears, threshold day, LWP (Payroll, 9.5). NULL = the company default
     * (PayrollSettings::payScaleFor()).
     *
     * @return BelongsTo<PayScaleProfile, $this>
     */
    public function payScaleProfile(): BelongsTo
    {
        return $this->belongsTo(PayScaleProfile::class);
    }

    /** @return BelongsTo<self, $this> */
    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supervisor_id');
    }

    /** @return BelongsTo<self, $this> */
    public function dottedLineManager(): BelongsTo
    {
        return $this->belongsTo(self::class, 'dotted_line_manager_id');
    }

    /** @return HasMany<self, $this> */
    public function subordinates(): HasMany
    {
        return $this->hasMany(self::class, 'supervisor_id');
    }

    /**
     * Everyone below this employee in the supervisor chain (direct reports, their reports, ...): a manager's team.
     * withoutGlobalScope: a manager sees their team even without HR access to those entities.
     *
     * @return list<int>
     */
    public function teamIds(): array
    {
        $bosses = static::withoutGlobalScope(VisibleToCurrentUser::class)->whereNotNull('supervisor_id')->pluck('supervisor_id', 'id')->all();
        $team = [];
        $level = [$this->id];
        while ($level !== []) {
            $level = array_keys(array_filter($bosses, fn ($boss) => in_array((int) $boss, $level, true)));
            $level = array_values(array_diff($level, $team, [$this->id]));   // a loop in the data never runs forever
            $team = [...$team, ...$level];
        }

        return $team;
    }

    /**
     * The login (central database). Works across the two databases because Eloquent runs a
     * separate query on User's own connection; it just cannot be a real foreign key.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
