<?php

namespace App\Modules\Payroll\Database\Factories;

use App\Modules\Payroll\Models\HolidayProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Fake HolidayProfile rows: HolidayProfile::factory()->hasAttached($holidays)->create().
 *
 * @extends Factory<HolidayProfile>
 */
class HolidayProfileFactory extends Factory
{
    protected $model = HolidayProfile::class;

    public function definition(): array
    {
        return ['name' => 'Calendar '.fake()->unique()->numberBetween(1, 99999)];
    }
}
