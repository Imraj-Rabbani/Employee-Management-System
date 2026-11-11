<?php

namespace App\Modules\Payroll\Models;

use App\Core\Models\TenantModel;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\OrgUnit;
use App\Modules\Payroll\Database\Factories\ShiftFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Working hours an employee is expected to keep, e.g. "General" 09:00–18:00 (legacy `shifts`). [TA-01]
 *
 * Two rules are kept by the model itself, so no screen can get them wrong:
 *   - is_overnight is worked out from the times: ends at or before it starts → ends the next day
 *   - only ONE default shift: saving a default takes the flag away from the others
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property int|null $org_unit_id
 * @property string $start_time
 * @property string $end_time
 * @property string $hours
 * @property int $grace_minutes
 * @property int|null $grace_limit_per_month
 * @property int $flexibility_minutes
 * @property string|null $half_day_hours
 * @property string|null $half_day_after
 * @property bool $is_overnight
 * @property bool $is_default
 * @property bool $geofence_enabled
 * @property string|null $geofence_lat
 * @property string|null $geofence_lng
 * @property int|null $geofence_radius_m
 * @property bool $is_active
 */
#[UseFactory(ShiftFactory::class)]
class Shift extends TenantModel
{
    /** @use HasFactory<ShiftFactory> */
    use HasFactory;

    protected $fillable = [
        'code', 'name', 'org_unit_id',
        'start_time', 'end_time', 'hours',
        'grace_minutes', 'grace_limit_per_month', 'flexibility_minutes',
        'half_day_hours', 'half_day_after',
        'is_overnight', 'is_default',
        'geofence_enabled', 'geofence_lat', 'geofence_lng', 'geofence_radius_m',
        'is_active',
    ];

    protected $attributes = ['is_active' => true];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'hours' => 'decimal:2',
            'half_day_hours' => 'decimal:2',
            'geofence_lat' => 'decimal:7',
            'geofence_lng' => 'decimal:7',
            'is_overnight' => 'boolean',
            'is_default' => 'boolean',
            'geofence_enabled' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Shift $shift) {
            // "HH:MM" and "HH:MM:SS" compare correctly as text.
            $shift->is_overnight = substr($shift->end_time, 0, 5) <= substr($shift->start_time, 0, 5);
        });

        static::saved(function (Shift $shift) {
            if ($shift->is_default) {
                static::whereKeyNot($shift->id)->where('is_default', true)->update(['is_default' => false]);
            }
        });
    }

    /** An employee's shift on a day, for forms: as processed, else their own, else the default. */
    public static function ofEmployeeOn(Employee $employee, string $date): ?self
    {
        $processed = AttendanceDay::query()->where('employee_id', $employee->id)->whereDate('date', $date)->value('shift_id');
        $id = $processed ?? $employee->shift_id;

        return ($id ? self::find($id) : null) ?? self::default();
    }

    /** The company's default shift (for employees without one), or null. */
    public static function default(): ?self
    {
        return static::where('is_default', true)->first();
    }

    /** "09:00–18:00", or "22:00–06:00 (+1)" for an overnight shift. */
    public function timesLabel(): string
    {
        return substr($this->start_time, 0, 5).'–'.substr($this->end_time, 0, 5).($this->is_overnight ? ' (+1)' : '');
    }

    /** "General · 09:00–18:00" for dropdowns. */
    public function optionLabel(): string
    {
        return $this->name.' · '.$this->timesLabel();
    }

    /** @return BelongsTo<OrgUnit, $this> */
    public function orgUnit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class);
    }

    /** @return HasMany<Employee, $this> */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
