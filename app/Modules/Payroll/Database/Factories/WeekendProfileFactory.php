<?php

namespace App\Modules\Payroll\Database\Factories;

use App\Modules\Payroll\Enums\Weekday;
use App\Modules\Payroll\Models\WeekendProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Fake WeekendProfile rows: Friday + Saturday by default (the usual weekend in Bangladesh).
 *
 * @extends Factory<WeekendProfile>
 */
class WeekendProfileFactory extends Factory
{
    protected $model = WeekendProfile::class;

    public function definition(): array
    {
        return [
            'name' => 'Weekend '.fake()->unique()->numberBetween(1, 99999),
            'day_one' => Weekday::Friday,
            'day_two' => Weekday::Saturday,
        ];
    }
}
