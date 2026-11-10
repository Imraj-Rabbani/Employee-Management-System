<?php

namespace App\Modules\Hrm\Models;

use App\Core\Models\TenantModel;
use App\Modules\Hrm\Database\Factories\InterviewFactorFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Something interviewers score, e.g. "Communication" (legacy `interviewfactors`, type 0). [REC-05]
 *
 *     InterviewFactor::ordered()->where('is_active', true)->get()   the score sheet, in order
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property string $weight decimal comes back as a string, e.g. "25.00"
 * @property bool $is_required
 * @property int $sequence
 * @property bool $is_active
 */
#[UseFactory(InterviewFactorFactory::class)]
class InterviewFactor extends TenantModel
{
    /** @use HasFactory<InterviewFactorFactory> */
    use HasFactory;

    protected $fillable = ['name', 'description', 'weight', 'is_required', 'sequence', 'is_active'];

    protected $attributes = ['weight' => 0, 'is_required' => false, 'sequence' => 0, 'is_active' => true];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'weight' => 'decimal:2',
            'is_required' => 'boolean',
            'sequence' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @param  Builder<self>  $query */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sequence')->orderBy('name');
    }
}
