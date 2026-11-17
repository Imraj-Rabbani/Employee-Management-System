<?php

namespace App\Modules\Hrm\Database\Factories;

use App\Modules\Hrm\Models\Entity;
use App\Modules\Hrm\Models\EntityType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Fake Entity rows for tests and demo data: Entity::factory()->create() (in a new entity type).
 *
 *     Entity::factory()->ofType($department)->create()
 *
 * @extends Factory<Entity>
 */
class EntityFactory extends Factory
{
    protected $model = Entity::class;

    public function definition(): array
    {
        return [
            'entity_type_id' => EntityType::factory(),
            'code' => 'EN-'.fake()->unique()->numerify('#####'),
            'name' => fake()->unique()->company(),
            'parent_id' => null,
        ];
    }

    public function ofType(EntityType $type): static
    {
        return $this->state(['entity_type_id' => $type->id]);
    }
}
