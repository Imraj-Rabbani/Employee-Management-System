<?php

namespace App\Modules\Hrm\Models;

use App\Core\Models\TenantModel;
use App\Modules\Hrm\Database\Factories\EntityFactory;
use App\Modules\Hrm\Scopes\VisibleToCurrentUser;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * One item of an entity type (legacy `entities`): Department "Finance", Branch "Gulshan", Division "Dhaka".
 *
 *     $entity->type                 its EntityType
 *     $entity->head                 the employee who heads it (approver type "entity head")
 *     $entity->parent               OPTIONAL entity of another type it sits under (e.g. Gulshan → Dhaka)
 *     $entity->label()              "Finance (Department)"
 *     Entity::options()             [id => "Finance (Department)"] for dropdowns
 *     Entity::optionsByType()       [type id => [id => "Finance", ...]] for one dropdown per type
 *
 * The list is FLAT: the parent is only used to find the next head up when an entity has none.
 * Saving an entity under itself, under an entity of its own type, or in a loop is refused.
 *
 * @property int $id
 * @property int $entity_type_id
 * @property string|null $code
 * @property string $name
 * @property int|null $parent_id
 * @property int|null $head_employee_id
 * @property bool $is_active
 */
#[UseFactory(EntityFactory::class)]
class Entity extends TenantModel
{
    /** @use HasFactory<EntityFactory> */
    use HasFactory;

    protected $fillable = ['entity_type_id', 'code', 'name', 'parent_id', 'head_employee_id', 'is_active'];

    protected $attributes = ['is_active' => true];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        // A model event: runs before every insert/update. Last line of defence; forms show parentProblem() first.
        static::saving(function (Entity $entity) {
            if ($entity->parent_id !== null && ($problem = $entity->parentProblem(static::find($entity->parent_id)))) {
                throw new LogicException($problem);
            }
        });
    }

    /**
     * Why $parent cannot be this entity's parent, or null when it can (null parent = always fine).
     * Rules: another entity, of ANOTHER type, and no loop (Gulshan → Dhaka → Gulshan).
     */
    public function parentProblem(?self $parent): ?string
    {
        if ($parent === null) {
            return null;
        }
        if ($this->exists && $parent->id === $this->id) {
            return __('An entity cannot be its own parent.');
        }
        if ($parent->entity_type_id === $this->entity_type_id) {
            return __('The parent must be an entity of another type.');
        }
        if ($this->exists && in_array($this->id, $parent->ancestorIds(), true)) {
            return __(':name is already under this entity.', ['name' => $parent->name]);
        }

        return null;
    }

    /** @return BelongsTo<EntityType, $this> */
    public function type(): BelongsTo
    {
        return $this->belongsTo(EntityType::class, 'entity_type_id');
    }

    /** @return BelongsTo<self, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<self, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('name');
    }

    /**
     * withoutGlobalScope: the head's name is shown even to users who cannot open that employee.
     *
     * @return BelongsTo<Employee, $this>
     */
    public function head(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'head_employee_id')->withoutGlobalScope(VisibleToCurrentUser::class);
    }

    /** @return HasMany<Position, $this> */
    public function positions(): HasMany
    {
        return $this->hasMany(Position::class);
    }

    /**
     * Current and past assignment rows of employees in this entity.
     *
     * @return HasMany<EmployeeEntity, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(EmployeeEntity::class);
    }

    /**
     * The open rows only: employees currently in this entity (withCount('currentAssignments') in lists).
     *
     * @return HasMany<EmployeeEntity, $this>
     */
    public function currentAssignments(): HasMany
    {
        return $this->hasMany(EmployeeEntity::class)->whereNull('effective_to');
    }

    /**
     * Parent, grandparent, ... (nearest first). Stops on bad (looping) data.
     *
     * @return list<int>
     */
    public function ancestorIds(): array
    {
        $parents = static::query()->whereNotNull('parent_id')->pluck('parent_id', 'id')->map(fn ($id) => (int) $id)->all();
        $ids = [];
        $current = $this->parent_id;

        while ($current !== null && ! in_array($current, $ids, true)) {
            $ids[] = $current;
            $current = $parents[$current] ?? null;
        }

        return $ids;
    }

    /** "Finance (Department)" */
    public function label(): string
    {
        return $this->name.' ('.$this->type->name.')';
    }

    /**
     * Every entity for one dropdown: [id => "Finance (Department)"], in type order then by name.
     *
     * @param  list<int>|null  $only  limit to these ids (null = all)
     * @return array<int, string>
     */
    public static function options(?array $only = null): array
    {
        return static::query()
            ->join('entity_types', 'entity_types.id', '=', 'entities.entity_type_id')
            ->when($only !== null, fn ($q) => $q->whereIn('entities.id', $only))
            ->orderBy('entity_types.sequence')->orderBy('entity_types.name')->orderBy('entities.name')
            ->get(['entities.id', 'entities.name', 'entity_types.name as type_name'])
            ->mapWithKeys(fn ($e) => [$e->id => $e->name.' ('.$e->getAttribute('type_name').')'])
            ->all();
    }

    /**
     * Active entities grouped by type, for "one dropdown per type" forms: [type id => [id => name]].
     * $keep: ids to include even when inactive (the ones a record already uses).
     *
     * @param  list<int>  $keep
     * @return array<int, array<int, string>>
     */
    public static function optionsByType(array $keep = []): array
    {
        return static::query()
            ->where(fn ($q) => $q->where('is_active', true)->orWhereIn('id', $keep))
            ->orderBy('name')
            ->get(['id', 'entity_type_id', 'name'])
            ->groupBy('entity_type_id')
            ->map(fn ($group) => $group->pluck('name', 'id')->all())
            ->all();
    }
}
