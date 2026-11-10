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
 * @property string|null $salary_min monthly gross band (6.7), decimal as a string
 * @property string|null $salary_max
 * @property string|null $description
 * @property bool $is_active
 */
#[UseFactory(GradeFactory::class)]
class Grade extends TenantModel
{
    /** @use HasFactory<GradeFactory> */
    use HasFactory;

    protected $fillable = ['code', 'name', 'sequence', 'salary_min', 'salary_max', 'description', 'is_active'];

    protected $attributes = ['is_active' => true];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'salary_min' => 'decimal:2', 'salary_max' => 'decimal:2'];
    }

    /** @return HasMany<Position, $this> */
    public function positions(): HasMany
    {
        return $this->hasMany(Position::class);
    }

    /** "30,000 – 45,000", "from 30,000", "up to 45,000" or null when the grade has no band. */
    public function salaryRange(): ?string
    {
        $min = $this->salary_min === null ? null : number_format((float) $this->salary_min);
        $max = $this->salary_max === null ? null : number_format((float) $this->salary_max);

        return match (true) {
            $min !== null && $max !== null => "{$min} – {$max}",
            $min !== null => __('from :amount', ['amount' => $min]),
            $max !== null => __('up to :amount', ['amount' => $max]),
            default => null,
        };
    }

    /** @param  Builder<self>  $query */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sequence')->orderBy('name');
    }
}
