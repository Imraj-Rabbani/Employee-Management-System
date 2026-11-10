<?php

namespace App\Modules\Payroll\Models;

use App\Core\Models\TenantModel;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Payroll\Database\Factories\HolidayProfileFactory;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A holiday calendar, e.g. "Head office" or "Factory" (legacy `holidayprofiles`). [TA-02]
 * Which holidays it contains: the pivot `holiday_holiday_profile` (legacy `holidayitems`).
 *
 * @property int $id
 * @property string $name
 * @property string|null $detail
 * @property bool $is_active
 */
#[UseFactory(HolidayProfileFactory::class)]
class HolidayProfile extends TenantModel
{
    /** @use HasFactory<HolidayProfileFactory> */
    use HasFactory;

    protected $fillable = ['name', 'detail', 'is_active'];

    protected $attributes = ['is_active' => true];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** The holiday on $date in this calendar, or null. Used by attendance processing (7.4). */
    public function holidayOn(CarbonInterface $date): ?Holiday
    {
        return $this->holidays()->onDate($date)->first();
    }

    /** @return BelongsToMany<Holiday, $this> */
    public function holidays(): BelongsToMany
    {
        return $this->belongsToMany(Holiday::class);
    }

    /** @return HasMany<Employee, $this> */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
