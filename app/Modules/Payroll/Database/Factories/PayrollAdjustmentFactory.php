<?php

namespace App\Modules\Payroll\Database\Factories;

use App\Modules\Hrm\Models\Employee;
use App\Modules\Payroll\Models\PayrollAdjustment;
use App\Modules\Payroll\Models\SalaryHead;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Fake adjustments: PayrollAdjustment::factory()->approved()->create() = an approved 1,000 earning for December 2026.
 *
 * @extends Factory<PayrollAdjustment>
 */
class PayrollAdjustmentFactory extends Factory
{
    protected $model = PayrollAdjustment::class;

    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'salary_head_id' => SalaryHead::factory(),
            'year' => 2026,
            'month' => 12,
            'amount' => '1000.00',
            'reason' => 'One-time allowance',
        ];
    }

    public function approved(): static
    {
        return $this->afterMaking(fn (PayrollAdjustment $adjustment) => $adjustment->approved_at = now());
    }
}
