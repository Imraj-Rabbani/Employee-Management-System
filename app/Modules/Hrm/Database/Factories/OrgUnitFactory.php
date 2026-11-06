<?php

namespace App\Modules\Hrm\Database\Factories;

use App\Modules\Hrm\Models\OrgUnit;
use App\Modules\Hrm\Models\OrgUnitType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Fake OrgUnit rows for tests and demo data: OrgUnit::factory()->create().
 *
 * @extends Factory<OrgUnit>
 */
class OrgUnitFactory extends Factory
{
    protected $model = OrgUnit::class;

    public function definition(): array
    {
        return [
            'org_unit_type_id' => OrgUnitType::factory(),
            'code' => 'OU-'.fake()->unique()->numerify('#####'),
            'name' => fake()->unique()->company(),
            'parent_id' => null,
        ];
    }

    /** A unit under $parent: OrgUnit::factory()->childOf($finance)->create(). */
    public function childOf(OrgUnit $parent): static
    {
        return $this->state(['parent_id' => $parent->id]);
    }
}
