<?php

namespace App\Modules\Hrm\Models;

use App\Core\Models\TenantModel;
use App\Modules\Hrm\Database\Factories\LeaveTypeFactory;
use App\Modules\Hrm\Enums\Gender;
use App\Modules\Hrm\Enums\LeaveBridging;
use App\Modules\Hrm\Enums\LeaveEarnType;
use App\Modules\Hrm\Enums\LeaveEligibleFrom;
use App\Modules\Hrm\Enums\Religion;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A kind of leave with its rules: Casual, Sick, Earned, Maternity ... (legacy `leavetypes`). [LV-01]
 * The rules are only stored here (8.1); LeaveRules (8.3) checks them on an application.
 * Day counts: NULL = no limit / not used (see the migration).
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $detail
 * @property int $sequence
 * @property Gender|null $gender
 * @property Religion|null $religion
 * @property LeaveEligibleFrom $eligible_from
 * @property int $eligible_after_months
 * @property int|null $max_times_in_service
 * @property bool $hr_only
 * @property float|null $min_days
 * @property float|null $max_days
 * @property int|null $max_calendar_days
 * @property bool $allow_half_day
 * @property bool $exclude_holidays
 * @property LeaveBridging $bridging
 * @property int $notice_days
 * @property bool $allow_post_facto
 * @property int|null $post_facto_days
 * @property bool $reason_required
 * @property float|null $attachment_after_days
 * @property bool $is_lwp
 * @property bool $requires_balance
 * @property float|null $max_balance
 * @property LeaveEarnType $earn_type
 * @property float|null $earn_per_days_worked
 * @property bool $can_carry_forward
 * @property float|null $carry_forward_limit
 * @property int|null $carry_forward_expiry_months
 * @property bool $is_encashable
 * @property float|null $encash_limit
 * @property bool $is_active
 */
#[UseFactory(LeaveTypeFactory::class)]
class LeaveType extends TenantModel
{
    /** @use HasFactory<LeaveTypeFactory> */
    use HasFactory;

    protected $fillable = [
        'code', 'name', 'detail', 'sequence',
        'gender', 'religion', 'eligible_from', 'eligible_after_months', 'max_times_in_service', 'hr_only',
        'min_days', 'max_days', 'max_calendar_days', 'allow_half_day', 'exclude_holidays', 'bridging',
        'notice_days', 'allow_post_facto', 'post_facto_days', 'reason_required', 'attachment_after_days',
        'is_lwp', 'requires_balance', 'max_balance',
        'earn_type', 'earn_per_days_worked',
        'can_carry_forward', 'carry_forward_limit', 'carry_forward_expiry_months',
        'is_encashable', 'encash_limit',
        'is_active',
    ];

    /** Same defaults as the database, so a new model (and the form) starts with them. */
    protected $attributes = [
        'sequence' => 0, 'eligible_from' => 0, 'eligible_after_months' => 0, 'bridging' => 0, 'notice_days' => 0,
        'earn_type' => 0, 'requires_balance' => true, 'is_active' => true,
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'religion' => Religion::class,
            'eligible_from' => LeaveEligibleFrom::class,
            'bridging' => LeaveBridging::class,
            'earn_type' => LeaveEarnType::class,
            'min_days' => 'float',
            'max_days' => 'float',
            'attachment_after_days' => 'float',
            'max_balance' => 'float',
            'earn_per_days_worked' => 'float',
            'carry_forward_limit' => 'float',
            'encash_limit' => 'float',
            'hr_only' => 'boolean',
            'allow_half_day' => 'boolean',
            'exclude_holidays' => 'boolean',
            'allow_post_facto' => 'boolean',
            'reason_required' => 'boolean',
            'is_lwp' => 'boolean',
            'requires_balance' => 'boolean',
            'can_carry_forward' => 'boolean',
            'is_encashable' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** "Casual leave (CL)" for dropdowns and lists. */
    public function optionLabel(): string
    {
        return "{$this->name} ({$this->code})";
    }

    /**
     * The order HR chose (sequence), then by name: LeaveType::ordered()->get().
     *
     * @param  Builder<self>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sequence')->orderBy('name');
    }

    /** @return HasMany<LeaveProfileItem, $this> */
    public function profileItems(): HasMany
    {
        return $this->hasMany(LeaveProfileItem::class);
    }
}
