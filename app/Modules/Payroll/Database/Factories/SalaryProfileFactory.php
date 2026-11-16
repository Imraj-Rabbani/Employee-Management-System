<?php

namespace App\Modules\Payroll\Database\Factories;

use App\Modules\Payroll\Models\SalaryProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Fake SalaryProfile rows. Items: $profile->syncItems([$head->id => ['value_type' => 'percent_basic', 'value' => '50']]).
 *
 * @extends Factory<SalaryProfile>
 */
class SalaryProfileFactory extends Factory
{
    protected $model = SalaryProfile::class;

    public function definition(): array
    {
        $n = fake()->unique()->numberBetween(1, 99999);

        return ['code' => 'SP'.$n, 'name' => 'Salary profile '.$n];
    }
}
