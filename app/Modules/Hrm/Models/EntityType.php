<?php

namespace App\Modules\Hrm\Models;

use App\Core\Models\TenantModel;
use App\Modules\Hrm\Database\Factories\EntityTypeFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A KIND of entity, named by the company (legacy `entitytypes`): Department, Division, Branch,
 * Location, Area ... A flat list: the company adds its own from HRM → Organization → Entity types.
 * Each employee has one entity of each type (employee_entities).
 *
 *     EntityType::ordered()->get()     in the company's order (sequence, then name)
 *
 * @property int $id
 * @property string $name
 * @property int $sequence
 * @property bool $is_active
 */
#[UseFactory(EntityTypeFactory::class)]
class EntityType extends TenantModel
{
    /** @use HasFactory<EntityTypeFactory> */
    use HasFactory;

    protected $fillable = ['name', 'sequence', 'is_active'];

    protected $attributes = ['sequence' => 0, 'is_active' => true];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return HasMany<Entity, $this> */
    public function entities(): HasMany
    {
        return $this->hasMany(Entity::class)->orderBy('name');
    }

    /** @param  Builder<self>  $query */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sequence')->orderBy('name');
    }
}
