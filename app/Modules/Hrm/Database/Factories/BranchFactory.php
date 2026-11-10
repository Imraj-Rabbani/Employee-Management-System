<?php

namespace App\Modules\Hrm\Database\Factories;

use App\Modules\Hrm\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Fake Branch rows for tests and demo data: Branch::factory()->create().
 *
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    protected $model = Branch::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->city().' Branch',
            'location' => fake()->address(),
        ];
    }
}
