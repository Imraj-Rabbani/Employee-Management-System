<?php

namespace App\Modules\Payroll\Database\Factories;

use App\Modules\Hrm\Models\Employee;
use App\Modules\Payroll\Models\OvertimeRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Fake overtime requests: OvertimeRequest::factory()->for($employee)->approved(2.5)->create(['date' => '2026-12-03']).
 * Default: 1 December 2026, 2 hours, not sent for approval.
 *
 * @extends Factory<OvertimeRequest>
 */
class OvertimeRequestFactory extends Factory
{
    protected $model = OvertimeRequest::class;

    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'date' => '2026-12-01',
            'hours' => 2,
            'detail' => 'Month-end closing',
        ];
    }

    /** Approved without a workflow (the approvals themselves are tested with the real engine). */
    public function approved(?float $hours = null): static
    {
        return $this->afterCreating(fn (OvertimeRequest $r) => $r->forceFill(['approved_hours' => $hours ?? $r->hours, 'approved_at' => now()])->save());
    }
}
