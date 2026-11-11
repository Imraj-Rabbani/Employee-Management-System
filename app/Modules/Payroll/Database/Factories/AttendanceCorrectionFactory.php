<?php

namespace App\Modules\Payroll\Database\Factories;

use App\Modules\Hrm\Models\Employee;
use App\Modules\Payroll\Models\AttendanceCorrection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Fake corrections: AttendanceCorrection::factory()->for($employee)->approved()->create(['date' => '2026-12-03', ...]).
 * Default: 1 December 2026, 09:00 – 18:00, not sent for approval.
 *
 * @extends Factory<AttendanceCorrection>
 */
class AttendanceCorrectionFactory extends Factory
{
    protected $model = AttendanceCorrection::class;

    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'date' => '2026-12-01',
            'requested_in_at' => '2026-12-01 09:00:00',
            'requested_out_at' => '2026-12-01 18:00:00',
            'reason' => 'Forgot to punch',
        ];
    }

    /** Approved without a workflow (the approvals themselves are tested with the real engine). */
    public function approved(): static
    {
        return $this->afterCreating(fn (AttendanceCorrection $c) => $c->forceFill(['approved_at' => now()])->save());
    }
}
