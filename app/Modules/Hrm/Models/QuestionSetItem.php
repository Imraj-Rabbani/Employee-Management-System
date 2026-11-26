<?php

namespace App\Modules\Hrm\Models;

use App\Core\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One question in a question set, with its weight (%) in THAT set and its place on the score sheet.
 *
 * @property int $id
 * @property int $question_set_id
 * @property int $interview_question_id
 * @property string $weight decimal as a string, e.g. "25.00"
 * @property int $sequence
 */
class QuestionSetItem extends TenantModel
{
    protected $fillable = ['question_set_id', 'interview_question_id', 'weight', 'sequence'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['weight' => 'decimal:2', 'sequence' => 'integer'];
    }

    /** @return BelongsTo<QuestionSet, $this> */
    public function questionSet(): BelongsTo
    {
        return $this->belongsTo(QuestionSet::class);
    }

    /** @return BelongsTo<InterviewQuestion, $this> */
    public function question(): BelongsTo
    {
        return $this->belongsTo(InterviewQuestion::class, 'interview_question_id');
    }
}
