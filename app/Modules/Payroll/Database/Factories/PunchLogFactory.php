<?php

namespace App\Modules\Payroll\Database\Factories;

use App\Modules\Hrm\Models\Employee;
use App\Modules\Payroll\Enums\PunchSource;
use App\Modules\Payroll\Models\PunchLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Fake punches: PunchLog::factory()->for($employee)->create(['punched_at' => '2026-12-01 08:55']).
 *
 * @extends Factory<PunchLog>
 */
class PunchLogFactory extends Factory
{
    protected $model = PunchLog::class;

    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'punched_at' => '2026-12-01 09:00:00',
            'source' => PunchSource::Manual,
        ];
    }
}
