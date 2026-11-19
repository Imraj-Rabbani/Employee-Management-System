<?php

namespace App\Modules\Payroll\Database\Factories;

use App\Core\Support\Period;
use App\Modules\Payroll\Enums\PayrollRunType;
use App\Modules\Payroll\Models\PayrollRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Fake payroll runs: PayrollRun::factory()->create() = a regular whole-company draft run for December 2026.
 * ->forPeriod(Period::of(2026, 11)) for another month.
 *
 * @extends Factory<PayrollRun>
 */
class PayrollRunFactory extends Factory
{
    protected $model = PayrollRun::class;

    public function definition(): array
    {
        $period = Period::of(2026, 12);

        return [
            'code' => 'PR-'.fake()->unique()->numberBetween(1, 999999),
            'year' => $period->year,
            'month' => $period->month,
            'cycle' => $period->cycle,
            'period_start' => $period->start(),
            'period_end' => $period->end(),
            'run_type' => PayrollRunType::Regular,
        ];
    }

    public function forPeriod(Period $period): static
    {
        return $this->state([
            'year' => $period->year,
            'month' => $period->month,
            'cycle' => $period->cycle,
            'period_start' => $period->start(),
            'period_end' => $period->end(),
        ]);
    }
}
