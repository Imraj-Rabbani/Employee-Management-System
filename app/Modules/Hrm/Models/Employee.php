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
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A person employed by the company (legacy `employees`). [EMP-01]
 *
 *     $employee->name                    "Rahim Uddin" (first + last)
 *     $employee->status                  EmployeeStatus::Active (enum, thanks to the cast)
 *     $employee->grade->name             org data (Phase 4) through belongsTo relations
 *     $employee->supervisor              another Employee (self-referencing)
 *     $employee->subordinates            employees who report to this one
 *     $employee->user                    the login (central DB), set in 5.7
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
 * @property int|null $org_unit_id
 * @property int|null $employment_type_id
 * @property int|null $designation_id
 * @property int|null $grade_id
 * @property int|null $position_id
 * @property int|null $branch_id
 * @property int|null $cost_center_id
 * @property int|null $supervisor_id
 * @property int|null $dotted_line_manager_id
 * @property int|null $shift_id
 * @property bool $has_shifting
 * @property int|null $weekend_profile_id
 * @property int|null $holiday_profile_id
 * @property int|null $leave_profile_id
 * @property int|null $salary_profile_id
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
     * user_id is deliberately not fillable: the login link is made only by the "link user" action (5.7),
     * never from a form.
     */
    protected $fillable = [
        'code', 'access_code', 'title', 'first_name', 'last_name', 'name_local',
        'gender', 'birth_date', 'marital_status', 'blood_group', 'religion', 'nationality',
        'national_id', 'tin', 'passport_no',
        'mobile', 'personal_email', 'office_email', 'photo_path', 'signature_path',
        'join_date', 'planned_confirmation_date', 'confirmation_status', 'confirmation_date',
        'org_unit_id', 'employment_type_id', 'designation_id', 'grade_id', 'position_id',
        'branch_id', 'cost_center_id', 'supervisor_id', 'dotted_line_manager_id',
        'shift_id', 'has_shifting', 'weekend_profile_id', 'holiday_profile_id', 'leave_profile_id',
        'salary_profile_id', 'pay_scale_profile_id', 'tax_profile_id', 'payment_method',
        'status', 'separation_date',
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
        ];
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

    /** @param  Builder<self>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', EmployeeStatus::Active);
    }

    /**
     * Would reporting to $supervisorId create a loop (A reports to B, B reports to ... A)?
     * Walks up the supervisor chain. withoutGlobalScope: the chain may pass through units you cannot see.
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

    /** @return BelongsTo<OrgUnit, $this> */
    public function orgUnit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class);
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

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<CostCenter, $this> */
    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
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
