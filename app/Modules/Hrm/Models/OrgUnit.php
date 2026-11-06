<?php

namespace App\Modules\Hrm\Models;

use App\Core\Models\TenantModel;
use App\Modules\Hrm\Database\Factories\OrgUnitFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use LogicException;

/**
 * One unit of the company's structure (legacy `entities`). The units form a TREE:
 * every unit has one parent (NULL for the top) and any number of children. [ORG-01]
 *
 *     $unit->parent                  the unit above (self-referencing belongsTo)
 *     $unit->children                the units directly below (self-referencing hasMany)
 *     $unit->ancestors()             parent, grandparent, ... up to the top
 *     $unit->descendantIds()         ids of every unit below, at any depth
 *     $unit->path()                  "Head Office › Finance › Accounts"
 *     OrgUnit::roots()->get()        the top units
 *
 * Saving a unit under itself or under one of its own children is refused (it would make a loop).
 *
 * @property int $id
 * @property int|null $parent_id
 * @property int $org_unit_type_id
 * @property string|null $code
 * @property string $name
 * @property int|null $head_employee_id
 * @property int|null $cost_center_id
 * @property bool $is_active
 */
#[UseFactory(OrgUnitFactory::class)]
class OrgUnit extends TenantModel
{
    /** @use HasFactory<OrgUnitFactory> */
    use HasFactory;

    protected $fillable = ['parent_id', 'org_unit_type_id', 'code', 'name', 'head_employee_id', 'cost_center_id', 'is_active'];

    protected $attributes = ['is_active' => true];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        // A model event: runs before every insert/update of an OrgUnit.
        static::saving(function (OrgUnit $unit) {
            if ($unit->parent_id !== null && $unit->exists
                && ($unit->parent_id === $unit->id || in_array($unit->parent_id, $unit->descendantIds(), true))) {
                throw new LogicException(__(':name cannot be placed under itself or one of its own sub-units.', ['name' => $unit->name]));
            }
        });
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

    /** @return BelongsTo<OrgUnitType, $this> */
    public function type(): BelongsTo
    {
        return $this->belongsTo(OrgUnitType::class, 'org_unit_type_id');
    }

    /** @return BelongsTo<CostCenter, $this> */
    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }

    /** @return HasMany<Position, $this> */
    public function positions(): HasMany
    {
        return $this->hasMany(Position::class);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeRoots(Builder $query): void
    {
        $query->whereNull('parent_id');
    }

    /**
     * Parent, grandparent, ... up to the top (nearest first).
     * Walks the parent map in PHP: one query, however deep the tree is.
     *
     * @return list<int>
     */
    public function ancestorIds(): array
    {
        $parents = self::parentMap();
        $ids = [];
        $current = $this->parent_id;

        while ($current !== null && ! in_array($current, $ids, true)) {   // in_array: stop on bad (looping) data
            $ids[] = $current;
            $current = $parents[$current] ?? null;
        }

        return $ids;
    }

    /** @return Collection<int, self> nearest first */
    public function ancestors(): Collection
    {
        $ids = $this->ancestorIds();
        $units = static::whereIn('id', $ids)->get()->keyBy('id');

        return collect($ids)->map(fn (int $id) => $units[$id])->values();
    }

    /**
     * Ids of every unit below this one, at any depth (children, grandchildren, ...).
     *
     * @return list<int>
     */
    public function descendantIds(): array
    {
        $children = [];
        foreach (self::parentMap() as $id => $parentId) {
            if ($parentId !== null) {
                $children[$parentId][] = $id;
            }
        }

        $ids = [];
        $queue = $children[$this->id] ?? [];
        while ($queue !== []) {
            $id = array_shift($queue);
            if (! in_array($id, $ids, true)) {
                $ids[] = $id;
                array_push($queue, ...($children[$id] ?? []));
            }
        }

        return $ids;
    }

    /** "Head Office › Finance › Accounts" */
    public function path(string $separator = ' › '): string
    {
        return $this->ancestors()->reverse()->pluck('name')->push($this->name)->implode($separator);
    }

    /**
     * Every unit's full path, for dropdowns: [id => "Head Office › Finance"], sorted by path.
     * Two queries in total, however many units there are.
     *
     * @param  list<int>  $except  ids to leave out (e.g. a unit and its own sub-units)
     * @return array<int, string>
     */
    public static function pathOptions(array $except = []): array
    {
        $units = static::query()->get(['id', 'parent_id', 'name'])->keyBy('id');
        $paths = [];

        foreach ($units as $id => $unit) {
            $names = [$unit->name];
            $seen = [$id];
            $parentId = $unit->parent_id;
            while ($parentId !== null && isset($units[$parentId]) && ! in_array($parentId, $seen, true)) {
                $seen[] = $parentId;
                array_unshift($names, $units[$parentId]->name);
                $parentId = $units[$parentId]->parent_id;
            }
            if (! in_array($id, $except, true)) {
                $paths[$id] = implode(' › ', $names);
            }
        }

        asort($paths, SORT_NATURAL | SORT_FLAG_CASE);

        return $paths;
    }

    /**
     * Every unit's parent: [id => parent_id]. One small query; org trees have hundreds of rows, not millions.
     *
     * @return array<int, int|null>
     */
    private static function parentMap(): array
    {
        return static::query()->pluck('parent_id', 'id')
            ->map(fn ($parent) => $parent === null ? null : (int) $parent)
            ->all();
    }
}
