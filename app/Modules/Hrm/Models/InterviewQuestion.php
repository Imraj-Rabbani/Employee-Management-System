<?php

namespace App\Modules\Hrm\Models;

use App\Core\Models\TenantModel;
use App\Modules\Hrm\Database\Factories\InterviewQuestionFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * One interview question, scored 1–5 by each interviewer, e.g. "Explains OOP clearly" (Recruitment → Config).
 * Questions are grouped into question sets; the weight of a question is set per set (QuestionSetItem).
 *
 *     InterviewQuestion::ordered()->where('is_active', true)->get()
 *
 * @property int $id
 * @property string $name the question
 * @property string|null $description a hint for interviewers: what to look for
 * @property int $sequence
 * @property bool $is_active
 */
#[UseFactory(InterviewQuestionFactory::class)]
class InterviewQuestion extends TenantModel
{
    /** @use HasFactory<InterviewQuestionFactory> */
    use HasFactory;

    protected $fillable = ['name', 'description', 'sequence', 'is_active'];

    protected $attributes = ['sequence' => 0, 'is_active' => true];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['sequence' => 'integer', 'is_active' => 'boolean'];
    }

    /** @param  Builder<self>  $query */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sequence')->orderBy('name');
    }
}
