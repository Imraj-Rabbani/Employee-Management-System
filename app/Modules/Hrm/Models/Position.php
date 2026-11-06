<?php

namespace App\Modules\Hrm\Models;

use App\Core\Models\TenantModel;
use App\Modules\Hrm\Database\Factories\PositionFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A seat in the structure, e.g. "Accounts Officer" in Finance (legacy `positions`). [ORG-01]
 * Positions form their own tree: parent = the position this one reports to (used by the org chart, 5.9).
 *
 * @property int $id
 * @property string|null $code
 * @property string $name
 * @property int|null $parent_id
 * @property int $org_unit_id
 * @property int|null $grade_id
 * @property int|null $cost_center_id
 * @property int|null $employee_id
 * @property bool $is_vacant
 * @property Carbon|null $effective_from
 * @property bool $is_active
 */
#[UseFactory(PositionFactory::class)]
class Position extends TenantModel
{
    /** @use HasFactory<PositionFactory> */
    use HasFactory;

    protected $fillable = [
        'code', 'name', 'parent_id', 'org_unit_id', 'grade_id', 'cost_center_id',
        'employee_id', 'is_vacant', 'effective_from', 'is_active',
    ];

    protected $attributes = ['is_vacant' => true, 'is_active' => true];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_vacant' => 'boolean', 'is_active' => 'boolean', 'effective_from' => 'date'];
    }

    /**
     * The position this one reports to.
     *
     * @return BelongsTo<self, $this>
     */
    public function reportsTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * Positions that report to this one.
     *
     * @return HasMany<self, $this>
     */
    public function directReports(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * Would reporting to $parentId create a loop (A reports to B, B reports to ... A)?
     * Walks up from $parentId; true if it reaches this position.
     */
    public function wouldLoopUnder(int $parentId): bool
    {
        $parents = static::query()->pluck('parent_id', 'id')->all();
        $current = $parentId;
        $seen = [];

        while ($current !== null && ! in_array($current, $seen, true)) {
            if ($current === $this->id) {
                return true;
            }
            $seen[] = $current;
            $current = $parents[$current] ?? null;
        }

        return false;
    }

    /** @return BelongsTo<OrgUnit, $this> */
    public function orgUnit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class);
    }

    /** @return BelongsTo<Grade, $this> */
    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    /** @return BelongsTo<CostCenter, $this> */
    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }
}
