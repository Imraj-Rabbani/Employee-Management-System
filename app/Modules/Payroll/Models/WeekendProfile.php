<?php

namespace App\Modules\Payroll\Models;

use App\Core\Models\TenantModel;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Payroll\Database\Factories\WeekendProfileFactory;
use App\Modules\Payroll\Enums\Weekday;
use App\Modules\Payroll\Enums\WeekParity;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The weekly days off of a group of employees, e.g. Friday + Saturday (legacy `weekendprofiles`). [TA-02]
 *
 * Up to two weekend days. Each may alternate: off only on the odd (1st, 3rd …) or even (2nd, 4th …)
 * occurrences of that weekday in the year, e.g. "every Friday + every 2nd Saturday".
 *
 * @property int $id
 * @property string|null $code
 * @property string $name
 * @property Weekday|null $day_one
 * @property Weekday|null $day_two
 * @property WeekParity|null $alternate_one
 * @property WeekParity|null $alternate_two
 * @property Weekday|null $home_office_day
 * @property string|null $detail
 * @property bool $is_active
 */
#[UseFactory(WeekendProfileFactory::class)]
class WeekendProfile extends TenantModel
{
    /** @use HasFactory<WeekendProfileFactory> */
    use HasFactory;

    protected $fillable = ['code', 'name', 'day_one', 'day_two', 'alternate_one', 'alternate_two', 'home_office_day', 'detail', 'is_active'];

    protected $attributes = ['is_active' => true];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'day_one' => Weekday::class,
            'day_two' => Weekday::class,
            'alternate_one' => WeekParity::class,
            'alternate_two' => WeekParity::class,
            'home_office_day' => Weekday::class,
            'is_active' => 'boolean',
        ];
    }

    /** Is $date a day off under this profile? Used by attendance processing (7.4). */
    public function isWeekend(CarbonInterface $date): bool
    {
        return $this->matches($date, $this->day_one, $this->alternate_one)
            || $this->matches($date, $this->day_two, $this->alternate_two);
    }

    private function matches(CarbonInterface $date, ?Weekday $day, ?WeekParity $parity): bool
    {
        if ($day === null || $date->dayOfWeek !== $day->value) {
            return false;
        }

        // The how-many-th of this weekday in the year: 1 Jan–7 Jan = 1st, 8 Jan–14 Jan = 2nd, …
        return $parity === null || $parity->matches(intdiv($date->dayOfYear - 1, 7) + 1);
    }

    /** "Friday, Saturday (even weeks)" for lists. */
    public function summary(): string
    {
        $parts = [];
        foreach ([[$this->day_one, $this->alternate_one], [$this->day_two, $this->alternate_two]] as [$day, $parity]) {
            if ($day !== null) {
                $parts[] = $day->label().match ($parity) {
                    WeekParity::Odd => ' ('.__('odd weeks').')',
                    WeekParity::Even => ' ('.__('even weeks').')',
                    null => '',
                };
            }
        }

        return $parts === [] ? __('No weekend') : implode(', ', $parts);
    }

    /** @return HasMany<Employee, $this> */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
