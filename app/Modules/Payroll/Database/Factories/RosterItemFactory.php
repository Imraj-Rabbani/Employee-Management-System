<?php

namespace App\Modules\Payroll\Database\Factories;

use App\Modules\Hrm\Models\Employee;
use App\Modules\Payroll\Models\Roster;
use App\Modules\Payroll\Models\RosterItem;
use App\Modules\Payroll\Models\Shift;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Fake roster cells: RosterItem::factory()->for($roster)->create(['employee_id' => ..., 'date' => ...]).
 *
 * @extends Factory<RosterItem>
 */
class RosterItemFactory extends Factory
{
    protected $model = RosterItem::class;

    public function definition(): array
    {
        return [
            'roster_id' => Roster::factory(),
            'employee_id' => Employee::factory(),
            'date' => '2026-12-01',
            'shift_id' => Shift::factory(),
        ];
    }

    /** A day off. */
    public function off(): static
    {
        return $this->state(['shift_id' => null, 'is_off' => true]);
    }
}
