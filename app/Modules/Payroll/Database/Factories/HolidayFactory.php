<?php

namespace App\Modules\Payroll\Database\Factories;

use App\Modules\Payroll\Models\Holiday;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Fake Holiday rows: Holiday::factory()->create(['date' => '2026-12-16', 'title' => 'Victory Day']).
 *
 * @extends Factory<Holiday>
 */
class HolidayFactory extends Factory
{
    protected $model = Holiday::class;

    public function definition(): array
    {
        return [
            'date' => fake()->dateTimeBetween('2026-01-01', '2026-12-31')->format('Y-m-d'),
            'title' => 'Holiday '.fake()->unique()->numberBetween(1, 99999),
        ];
    }
}
