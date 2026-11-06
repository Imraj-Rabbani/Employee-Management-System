<?php

namespace App\Modules\Hrm\Models;

use App\Core\Models\TenantModel;
use App\Modules\Hrm\Database\Factories\GradeFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Pay level (legacy `grades`). Always listed by `sequence`: Grade::ordered()->get().
 *
 * @property int $id
 * @property string|null $code
 * @property string $name
 * @property int $sequence
 * @property string|null $description
 * @property bool $is_active
 */
#[UseFactory(GradeFactory::class)]
class Grade extends TenantModel
{
    /** @use HasFactory<GradeFactory> */
    use HasFactory;

    protected $fillable = ['code', 'name', 'sequence', 'description', 'is_active'];

    protected $attributes = ['is_active' => true];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return HasMany<Position, $this> */
    public function positions(): HasMany
    {
        return $this->hasMany(Position::class);
    }

    /** @param  Builder<self>  $query */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sequence')->orderBy('name');
    }
}
