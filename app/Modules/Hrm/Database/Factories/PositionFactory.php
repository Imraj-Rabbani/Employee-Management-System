<?php

namespace App\Modules\Hrm\Database\Factories;

use App\Modules\Hrm\Models\Grade;
use App\Modules\Hrm\Models\OrgUnit;
use App\Modules\Hrm\Models\Position;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Fake Position rows for tests and demo data: Position::factory()->create().
 *
 * @extends Factory<Position>
 */
class PositionFactory extends Factory
{
    protected $model = Position::class;

    public function definition(): array
    {
        return [
            'code' => 'P-'.fake()->unique()->numerify('#####'),
            'name' => fake()->jobTitle(),
            'org_unit_id' => OrgUnit::factory(),
            'grade_id' => Grade::factory(),
            'is_vacant' => true,
        ];
    }

    /** Reports to $boss: Position::factory()->reportingTo($manager)->create(). */
    public function reportingTo(Position $boss): static
    {
        return $this->state(['parent_id' => $boss->id, 'org_unit_id' => $boss->org_unit_id]);
    }
}
