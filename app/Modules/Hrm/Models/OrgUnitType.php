<?php

namespace App\Modules\Hrm\Models;

use App\Core\Models\TenantModel;
use App\Modules\Hrm\Database\Factories\OrgUnitTypeFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A level of the company's structure, named by the company (legacy `entitytypes`):
 * e.g. Company → Division → Department → Section, or SSS's Function → Department → Location.
 * parent_type_id says which level a unit of this type normally sits under.
 *
 * @property int $id
 * @property string $name
 * @property int|null $parent_type_id
 * @property int $sequence
 * @property bool $is_active
 */
#[UseFactory(OrgUnitTypeFactory::class)]
class OrgUnitType extends TenantModel
{
    /** @use HasFactory<OrgUnitTypeFactory> */
    use HasFactory;

    protected $fillable = ['name', 'parent_type_id', 'sequence', 'is_active'];

    protected $attributes = ['sequence' => 0, 'is_active' => true];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return BelongsTo<self, $this> */
    public function parentType(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_type_id');
    }

    /** @return HasMany<OrgUnit, $this> */
    public function units(): HasMany
    {
        return $this->hasMany(OrgUnit::class);
    }
}
