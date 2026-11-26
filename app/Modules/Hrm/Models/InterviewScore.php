<?php

namespace App\Modules\Hrm\Models;

use App\Core\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One panelist's score for one interview question.
 * `weight` is the question's weight (in the stage's question set) when the score was given.
 *
 * @property int $id
 * @property int $interview_panelist_id
 * @property int $interview_question_id
 * @property int $score 1 … InterviewScoring::MAX
 * @property string $weight decimal as a string, e.g. "25.00"
 * @property string|null $note
 */
class InterviewScore extends TenantModel
{
    protected $fillable = ['interview_panelist_id', 'interview_question_id', 'score', 'weight', 'note'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['score' => 'integer', 'weight' => 'decimal:2'];
    }

    /** @return BelongsTo<InterviewPanelist, $this> */
    public function panelist(): BelongsTo
    {
        return $this->belongsTo(InterviewPanelist::class, 'interview_panelist_id');
    }

    /** @return BelongsTo<InterviewQuestion, $this> */
    public function question(): BelongsTo
    {
        return $this->belongsTo(InterviewQuestion::class, 'interview_question_id');
    }
}
