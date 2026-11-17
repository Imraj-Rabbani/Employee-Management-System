<?php

namespace App\Modules\Hrm\Database\Factories;

use App\Modules\Hrm\Models\EntityType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Fake EntityType rows for tests and demo data: EntityType::factory()->create().
 *
 * @extends Factory<EntityType>
 */
class EntityTypeFactory extends Factory
{
    protected $model = EntityType::class;

    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Department', 'Division', 'Branch', 'Location', 'Area', 'Zone']).' '.fake()->unique()->numberBetween(1, 99999),
            'sequence' => fake()->numberBetween(1, 10),
        ];
    }
}
