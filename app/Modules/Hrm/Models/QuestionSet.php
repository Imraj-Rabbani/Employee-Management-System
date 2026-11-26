<?php

namespace App\Modules\Hrm\Models;

use App\Core\Models\TenantModel;
use App\Modules\Hrm\Database\Factories\QuestionSetFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A named group of interview questions, e.g. "Technical – Software" (Recruitment → Config → Question sets).
 * An interview stage uses one set: its interviewers score every question of the set.
 *
 *     $set->items                       the questions with their weight in this set, in order
 *     $set->totalWeight()               100.0 when the weights are complete
 *     $set->isUsed()                    a stage uses it: its questions and weights can no longer change
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property bool $is_active
 */
#[UseFactory(QuestionSetFactory::class)]
class QuestionSet extends TenantModel
{
    /** @use HasFactory<QuestionSetFactory> */
    use HasFactory;

    protected $fillable = ['name', 'description', 'is_active'];

    protected $attributes = ['is_active' => true];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return HasMany<QuestionSetItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(QuestionSetItem::class)->orderBy('sequence')->orderBy('id');
    }

    /** @return HasMany<InterviewStage, $this> */
    public function stages(): HasMany
    {
        return $this->hasMany(InterviewStage::class);
    }

    public function totalWeight(): float
    {
        return round((float) $this->items->sum(fn (QuestionSetItem $item) => (float) $item->weight), 2);
    }

    /** A stage uses this set: changing its questions would make the stage's score sheets unequal. */
    public function isUsed(): bool
    {
        return $this->stages()->exists();
    }
}
