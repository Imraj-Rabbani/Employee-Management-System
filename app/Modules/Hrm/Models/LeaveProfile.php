<?php

namespace App\Modules\Hrm\Models;

use App\Core\Models\TenantModel;
use App\Modules\Hrm\Database\Factories\LeaveProfileFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A set of leave entitlements, e.g. "Staff" = Casual 10, Sick 14, Earned 1 per 18 days (legacy `leaveprofiles`).
 * Each employee has one (employees.leave_profile_id). [LV-02]
 *
 *     $profile->entitlements()        [leave_type_id => days per year]
 *     $profile->syncEntitlements([3 => 10.0, 5 => 14.0])   saves them, one history entry
 *
 * @property int $id
 * @property string $name
 * @property string|null $detail
 * @property bool $is_active
 */
#[UseFactory(LeaveProfileFactory::class)]
class LeaveProfile extends TenantModel
{
    /** @use HasFactory<LeaveProfileFactory> */
    use HasFactory;

    protected $fillable = ['name', 'detail', 'is_active'];

    protected $attributes = ['is_active' => true];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return array<int, float> leave_type_id => days per year, in leave type id order */
    public function entitlements(): array
    {
        return $this->items()->orderBy('leave_type_id')->pluck('days_per_year', 'leave_type_id')
            ->map(fn ($days) => (float) $days)->all();
    }

    /**
     * Makes the profile hold exactly these leave types with these days: missing types are removed,
     * new ones added, changed days updated. Like Role::syncPermissions(): ONE readable history entry on the
     * profile (the item rows log nothing themselves).
     *
     * @param  array<int, float|int|string>  $days  leave_type_id => days per year
     */
    public function syncEntitlements(array $days): void
    {
        // As floats, like entitlements(): otherwise 12 and 12.0 count as a change.
        $days = array_map(fn ($value) => (float) $value, $days);
        ksort($days);
        $old = $this->entitlements();

        $this->getConnection()->transaction(function () use ($days) {
            $this->items()->whereNotIn('leave_type_id', array_keys($days))->delete();
            foreach ($days as $typeId => $value) {
                $this->items()->updateOrCreate(['leave_type_id' => $typeId], ['days_per_year' => $value]);
            }
        });

        if ($old !== $days) {
            activity()->performedOn($this)->event('updated')
                ->withChanges(['old' => ['entitlements' => $this->readable($old)], 'attributes' => ['entitlements' => $this->readable($days)]])
                ->log('updated');
        }
    }

    /**
     * For the History tab: "CL 10, SL 14" instead of ids.
     *
     * @param  array<int, float>  $days
     */
    private function readable(array $days): string
    {
        $codes = LeaveType::whereIn('id', array_keys($days))->pluck('code', 'id');

        return collect($days)->map(fn ($value, $id) => ($codes[$id] ?? "#{$id}").' '.$value)->implode(', ');
    }

    /** @return HasMany<LeaveProfileItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(LeaveProfileItem::class);
    }

    /** @return HasMany<Employee, $this> */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
