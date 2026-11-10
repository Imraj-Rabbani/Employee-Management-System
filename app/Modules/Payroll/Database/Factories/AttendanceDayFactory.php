<?php

namespace App\Modules\Payroll\Database\Factories;

use App\Modules\Hrm\Models\Employee;
use App\Modules\Payroll\Enums\AttendanceStatus;
use App\Modules\Payroll\Enums\DayType;
use App\Modules\Payroll\Models\AttendanceDay;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Fake processed days: AttendanceDay::factory()->for($employee)->create(['date' => '2026-12-01']).
 *
 * @extends Factory<AttendanceDay>
 */
class AttendanceDayFactory extends Factory
{
    protected $model = AttendanceDay::class;

    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'date' => '2026-12-01',
            'day_type' => DayType::Workday,
            'status' => AttendanceStatus::Present,
            'in_at' => '2026-12-01 09:00:00',
            'out_at' => '2026-12-01 18:00:00',
            'punch_count' => 2,
            'worked_minutes' => 540,
        ];
    }

    public function absent(): static
    {
        return $this->state(['status' => AttendanceStatus::Absent, 'in_at' => null, 'out_at' => null, 'punch_count' => 0, 'worked_minutes' => 0]);
    }
}
