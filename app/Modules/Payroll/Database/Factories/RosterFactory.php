<?php

namespace App\Modules\Payroll\Database\Factories;

use App\Modules\Hrm\Models\OrgUnit;
use App\Modules\Payroll\Models\Roster;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Fake rosters: one week from 1 December 2026 by default. Roster::factory()->approved()->create().
 *
 * @extends Factory<Roster>
 */
class RosterFactory extends Factory
{
    protected $model = Roster::class;

    public function definition(): array
    {
        return [
            'org_unit_id' => OrgUnit::factory(),
            'title' => 'Roster '.fake()->unique()->numberBetween(1, 99999),
            'from_date' => '2026-12-01',
            'to_date' => '2026-12-07',
        ];
    }

    /** As if the workflow approved it (without approval rows). */
    public function approved(): static
    {
        return $this->afterCreating(fn (Roster $roster) => $roster->forceFill(['approved_at' => now()])->save());
    }
}
