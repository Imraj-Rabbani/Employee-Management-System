<?php

namespace App\Modules\Payroll\Database\Factories;

use App\Modules\Payroll\Models\PayScaleProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PayScaleProfile> */
class PayScaleProfileFactory extends Factory
{
    protected $model = PayScaleProfile::class;

    public function definition(): array
    {
        $n = fake()->unique()->numberBetween(1, 99999);

        return ['code' => 'PS'.$n, 'name' => 'Pay scale '.$n];
    }
}
