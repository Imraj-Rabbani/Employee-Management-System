<?php

namespace App\Modules\Hrm\Database\Factories;

use App\Modules\Hrm\Models\CostCenter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Fake CostCenter rows for tests and demo data: CostCenter::factory()->create().
 *
 * @extends Factory<CostCenter>
 */
class CostCenterFactory extends Factory
{
    protected $model = CostCenter::class;

    public function definition(): array
    {
        return [
            'code' => 'CC-'.fake()->unique()->numerify('####'),
            'name' => fake()->words(2, true),
            'account_code' => fake()->numerify('5###'),
        ];
    }
}
