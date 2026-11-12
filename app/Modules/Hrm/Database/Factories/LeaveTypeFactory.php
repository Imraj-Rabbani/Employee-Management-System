<?php

namespace App\Modules\Hrm\Database\Factories;

use App\Modules\Hrm\Models\LeaveType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Fake LeaveType rows: LeaveType::factory()->create(['code' => 'CL', 'name' => 'Casual leave']).
 * Every rule at its default (no limits, balance required), so a test sets only the rule it is about.
 *
 * @extends Factory<LeaveType>
 */
class LeaveTypeFactory extends Factory
{
    protected $model = LeaveType::class;

    public function definition(): array
    {
        $n = fake()->unique()->numberBetween(1, 99999);

        return ['code' => "L{$n}", 'name' => "Leave {$n}"];
    }
}
