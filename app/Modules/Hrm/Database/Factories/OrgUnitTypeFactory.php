<?php

namespace App\Modules\Hrm\Database\Factories;

use App\Modules\Hrm\Models\OrgUnitType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Fake OrgUnitType rows for tests and demo data: OrgUnitType::factory()->create().
 *
 * @extends Factory<OrgUnitType>
 */
class OrgUnitTypeFactory extends Factory
{
    protected $model = OrgUnitType::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement(['Company', 'Division', 'Department', 'Section', 'Unit', 'Zone', 'Area', 'Region']).' '.fake()->unique()->numberBetween(1, 9999),
            'sequence' => fake()->numberBetween(1, 10),
        ];
    }
}
