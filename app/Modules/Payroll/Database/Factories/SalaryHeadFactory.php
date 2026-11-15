<?php

namespace App\Modules\Payroll\Database\Factories;

use App\Modules\Payroll\Enums\CalcMethod;
use App\Modules\Payroll\Enums\HeadType;
use App\Modules\Payroll\Models\SalaryHead;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Fake SalaryHead rows for tests and demo data: SalaryHead::factory()->create().
 * A fixed earning by default; ->percentOfBasic(50) for a "% of basic" head.
 *
 * @extends Factory<SalaryHead>
 */
class SalaryHeadFactory extends Factory
{
    protected $model = SalaryHead::class;

    public function definition(): array
    {
        $n = fake()->unique()->numberBetween(1, 99999);

        return [
            'code' => 'H'.$n,
            'name' => 'Head '.$n,
            'type' => HeadType::Earning,
            'calc_method' => CalcMethod::Fixed,
            'amount' => '1000.00',
            'sequence' => fake()->numberBetween(1, 50),
        ];
    }

    public function percentOfBasic(string $rate): static
    {
        return $this->state(['calc_method' => CalcMethod::PercentOfBasic, 'amount' => null, 'rate' => $rate]);
    }
}
